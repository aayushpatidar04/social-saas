<?php

namespace App\Http\Controllers;

use App\Jobs\SyncFacebookComments;
use App\Jobs\SyncInstagramComments;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Services\Credentials\CredentialResolver;
use App\Services\Meta\MetaConnectionException;
use App\Services\Meta\MetaOAuthService;
use App\Services\Meta\OAuthState;
use App\Services\Meta\SocialAccountConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Connect Facebook Pages (and the Instagram accounts linked to them).
 *
 *   login     -> Facebook Login dialog
 *   callback  -> exchange code, load every Page the user manages, show the picker
 *   select    -> page picker
 *   store     -> connect only what the user ticked
 *
 * Can be run any number of times, with different Facebook users, to bring in
 * Pages from several Facebook accounts.
 */
class FacebookAuthController extends Controller
{
    public function login(CredentialResolver $resolver, MetaOAuthService $meta)
    {
        $user = Auth::user();

        if (! $user->canManageOrganization()) {
            return $this->back('error', 'Only organization admins and managers can connect accounts.');
        }

        $credential = $resolver->forPlatform('facebook', $user->organization_id);
        if (! $credential) {
            return $this->back('error', 'Facebook is not configured yet. Ask your administrator to add the Meta app credentials.');
        }

        $state = OAuthState::issue('facebook', $user->id, $user->organization_id, $credential->id);

        return redirect()->away($meta->facebookAuthUrl($credential, $state));
    }

    public function callback(Request $request, MetaOAuthService $meta)
    {
        $user = Auth::user();

        if ($request->filled('error')) {
            OAuthState::consume($request->query('state'), 'facebook', $user->id);

            return $this->back('error', 'Facebook login was cancelled: '.($request->query('error_description') ?: $request->query('error')));
        }

        $state = OAuthState::consume($request->query('state'), 'facebook', $user->id);
        if (! $state || ! $request->filled('code')) {
            return $this->back('error', 'The Facebook login session expired. Please try again.');
        }

        $credential = PlatformCredential::find($state['credential_id']);
        if (! $credential) {
            return $this->back('error', 'The Meta app used for this login no longer exists.');
        }

        try {
            $token = $meta->facebookUserToken($credential, $request->query('code'));
            $pages = $meta->facebookPages($credential, $token['access_token']);
        } catch (MetaConnectionException $e) {
            return $this->back('error', $e->getMessage());
        }

        if (! $pages) {
            return $this->back('error', 'No Facebook Pages were found for this login. Make sure you are an admin of a Page and granted access to it.');
        }

        $pendingId = Str::random(40);
        Cache::put("meta_pending:{$pendingId}", Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'credential_id' => $credential->id,
            'pages' => $pages,
        ])), now()->addMinutes((int) config('integrations.pending_selection_ttl', 15)));

        return redirect()->route('auth.facebook.select', ['pending' => $pendingId]);
    }

    public function select(Request $request)
    {
        $pending = $this->loadPending($request->query('pending'));
        if (! $pending) {
            return $this->back('error', 'The page selection expired. Please connect Facebook again.');
        }

        $pageIds = collect($pending['pages'])->pluck('id');
        $igIds = collect($pending['pages'])->pluck('instagram.id')->filter();

        $known = SocialAccount::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('platform', 'facebook')->whereIn('platform_account_id', $pageIds)
                ->orWhere(fn ($q) => $q->where('platform', 'instagram')->whereIn('platform_account_id', $igIds)))
            ->get(['platform', 'platform_account_id', 'organization_id', 'status'])
            ->keyBy(fn ($a) => $a->platform.':'.$a->platform_account_id);

        $organization = Auth::user()->organization;
        $stateOf = function (string $platform, string $id) use ($known, $organization) {
            $a = $known->get("{$platform}:{$id}");
            if (! $a) {
                return 'available';
            }
            if ((int) $a->organization_id !== (int) $organization->id) {
                return 'other_organization';
            }

            return $a->status === 'disconnected' ? 'available' : 'connected';
        };

        return Inertia::render('Settings/SelectMetaPages', [
            'pending' => $request->query('pending'),
            'pages' => collect($pending['pages'])->map(fn ($p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'category' => $p['category'],
                'picture' => $p['picture'],
                'state' => $stateOf('facebook', $p['id']),
                'instagram' => $p['instagram'] ? $p['instagram'] + ['state' => $stateOf('instagram', $p['instagram']['id'])] : null,
            ])->values(),
            'limits' => [
                'used' => $organization->socialAccounts()->where('status', '!=', 'disconnected')->count(),
                'max' => $organization->limit('social_accounts'),
            ],
        ]);
    }

    public function store(Request $request, SocialAccountConnector $connector, MetaOAuthService $meta)
    {
        $data = $request->validate([
            'pending' => ['required', 'string'],
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.page_id' => ['required', 'string'],
            'selections.*.page' => ['boolean'],
            'selections.*.instagram' => ['boolean'],
        ]);

        $pending = $this->loadPending($data['pending']);
        if (! $pending) {
            return $this->back('error', 'The page selection expired. Please connect Facebook again.');
        }

        $user = Auth::user();
        $organization = $user->organization;
        $credential = PlatformCredential::find($pending['credential_id']);
        $pages = collect($pending['pages'])->keyBy('id');

        $connected = [];
        $errors = [];

        foreach ($data['selections'] as $sel) {
            $page = $pages->get($sel['page_id']);
            if (! $page) {
                continue; // not part of this login: ignore
            }

            $wantPage = (bool) ($sel['page'] ?? false);
            $wantIg = (bool) ($sel['instagram'] ?? false) && $page['instagram'];
            if (! $wantPage && ! $wantIg) {
                continue;
            }

            $pageAccount = null;
            try {
                if ($wantPage) {
                    $pageAccount = $connector->connect($organization, $user, 'facebook', $page['id'], [
                        'name' => $page['name'],
                        'access_token' => $page['access_token'],
                        'auth_mode' => 'facebook_login',
                        'metadata' => array_filter([
                            'category' => $page['category'],
                            'picture' => $page['picture'],
                        ]),
                    ], $credential);
                    $connected[] = $pageAccount;
                }
            } catch (MetaConnectionException $e) {
                $errors[] = "{$page['name']}: ".$e->getMessage();
            }

            try {
                if ($wantIg) {
                    $parentId = $pageAccount?->id
                        ?? SocialAccount::where('platform', 'facebook')->where('platform_account_id', $page['id'])->value('id');

                    $ig = $page['instagram'];
                    $connected[] = $connector->connect($organization, $user, 'instagram', $ig['id'], [
                        'name' => $ig['username'] ? '@'.$ig['username'] : ($ig['name'] ?? 'Instagram '.$ig['id']),
                        'handle' => $ig['username'] ? '@'.$ig['username'] : '',
                        // Instagram reached through a Page uses that Page's token.
                        'access_token' => $page['access_token'],
                        'auth_mode' => 'facebook_login',
                        'parent_account_id' => $parentId,
                        'metadata' => array_filter([
                            'facebook_page_id' => $page['id'],
                            'picture' => $ig['picture'],
                        ]),
                    ], $credential);
                }
            } catch (MetaConnectionException $e) {
                $errors[] = "Instagram ({$page['name']}): ".$e->getMessage();
            }

            // One Page subscription delivers both the Page feed and linked Instagram comments.
            if ($pageAccount || $wantIg) {
                $subscribed = $credential && $meta->subscribePage($credential, $page['id'], $page['access_token']);
                SocialAccount::whereIn('id', collect($connected)
                    ->filter(fn ($a) => ($a->metadata['facebook_page_id'] ?? $a->platform_account_id) === $page['id'])
                    ->pluck('id'))
                    ->update(['webhook_subscribed_at' => $subscribed ? now() : null]);
                if (! $subscribed) {
                    $errors[] = "{$page['name']}: connected, but Meta did not accept the webhook subscription (comments will still sync on schedule).";
                }
            }
        }

        Cache::forget("meta_pending:{$data['pending']}");

        foreach ($connected as $account) {
            try {
                $account->platform === 'instagram'
                    ? SyncInstagramComments::dispatch($account)
                    : SyncFacebookComments::dispatch($account);
            } catch (\Throwable $e) {
                Log::warning('Initial sync could not be queued', ['account' => $account->id, 'error' => $e->getMessage()]);
            }
        }

        $flash = [];
        if ($connected) {
            $flash['success'] = 'Connected '.count($connected).' account'.(count($connected) === 1 ? '' : 's').'.';
        }
        if ($errors) {
            $flash['error'] = implode(' ', $errors);
        }

        return redirect()->route('settings.social-accounts.index')->with($flash ?: ['error' => 'Nothing was selected.']);
    }

    private function loadPending(?string $id): ?array
    {
        if (! $id) {
            return null;
        }

        $blob = Cache::get("meta_pending:{$id}");
        if (! $blob) {
            return null;
        }

        $data = json_decode(Crypt::decryptString($blob), true);

        // Only the user (and organization) that started the login may finish it.
        return ($data['user_id'] ?? null) === Auth::id()
            && ($data['organization_id'] ?? null) === Auth::user()->organization_id
            ? $data : null;
    }

    private function back(string $type, string $message)
    {
        return redirect()->route('settings.social-accounts.index')->with($type, $message);
    }
}
