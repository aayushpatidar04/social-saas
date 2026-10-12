<?php



namespace App\Http\Controllers;

use App\Jobs\SyncFacebookComments;
use App\Jobs\SyncInstagramComments;
use App\Jobs\SyncLinkedInComments;
use App\Jobs\SyncTwitterComments;
use App\Jobs\SyncYoutubeComments;
use App\Models\SocialAccount;
use App\Services\Credentials\CredentialResolver;
use App\Services\Meta\MetaApi;
use App\Services\Meta\MetaOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SocialAccountController extends Controller
{
    public function index(CredentialResolver $resolver)
    {
        $user = Auth::user();
        $organization = $user->organization;

        $accounts = $organization->socialAccounts()
            ->with(['user:id,name', 'parent:id,platform_account_name'])
            ->latest()
            ->get()
            ->map(fn (SocialAccount $a) => array_merge($a->toArray(), [
                'reconnect_url' => $this->reauthorizeUrl($a),
            ]));

        return Inertia::render('Settings/SocialAccounts', [
            'accounts' => $accounts,
            // which platforms have an app configured for this organization
            'availability' => $resolver->availability($organization->id),
            'connect_urls' => [
                'facebook' => route('auth.facebook'),
                'instagram' => route('auth.instagram'),
                'youtube' => route('youtube.login'),
                'twitter' => route('twitter.login'),
                'linkedin' => route('linkedin.login'),
            ],
            'limits' => [
                'used' => $organization->socialAccounts()->where('status', '!=', 'disconnected')->count(),
                'max' => $organization->limit('social_accounts'),
            ],
            'can_manage' => $user->canManageOrganization(),
            'available_platforms' => ['facebook', 'instagram', 'youtube', 'twitter', 'linkedin'],
        ]);
    }

    /** Where to send the user when the stored token no longer works. */
    private function reauthorizeUrl(SocialAccount $a): ?string
    {
        return match (true) {
            $a->isInstagramLogin() => route('auth.instagram'),
            in_array($a->platform, ['facebook', 'instagram'], true) => route('auth.facebook'),
            $a->platform === 'youtube' => route('youtube.login'),
            $a->platform === 'twitter' => route('twitter.login'),
            $a->platform === 'linkedin' => route('linkedin.login'),
            default => null,
        };
    }

    private function denyUnlessManager()
    {
        if (! Auth::user()->canManageOrganization()) {
            abort(response()->json(['error' => 'Only organization admins and managers can do this.'], 403));
        }
    }

    public function sync(Request $request, SocialAccount $account)
    {
        if ($account->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $fullSync = $request->boolean('full_sync', false);

            if ($fullSync) {
                $options = [
                    'post_window_days' => 0,
                    'comment_window_days' => 0,
                    'full_sync' => true,
                ];
            } else {
                $options = [
                    'post_window_days' => config('sync.post_window_days', 30),
                    'comment_window_days' => config('sync.comment_window_days', 7),
                    'full_sync' => false,
                ];
            }

            match ($account->platform) {
                'facebook' => SyncFacebookComments::dispatch($account, $options, $fullSync),
                'instagram' => SyncInstagramComments::dispatch($account, $options, $fullSync),
                'youtube' => SyncYoutubeComments::dispatch($account, $options, $fullSync),
                'twitter' => SyncTwitterComments::dispatch($account, $options, $fullSync),
                'linkedin' => SyncLinkedInComments::dispatch($account, $options, $fullSync),
                default => Log::warning('No sync job for platform: ' . $account->platform),
            };

            $message = $fullSync
                ? 'Full sync started! All posts and comments will be fetched. AI responses will NOT be generated.'
                : 'Sync started! New comments will be updated shortly.';

            return response()->json([
                'message' => $message,
                'status' => 'processing',
                'full_sync' => $fullSync,
            ]);
        } catch (\Exception $e) {
            Log::error('Sync error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to start sync: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function disconnect(Request $request, SocialAccount $account)
    {
        $this->denyUnlessManager();

        if ($account->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $account->update([
                'status' => 'disconnected',
                'is_active' => false,
            ]);

            return response()->json([
                'message' => 'Account disconnected successfully',
                'status' => 'disconnected',
            ]);
        } catch (\Exception $e) {
            Log::error('Disconnect error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to disconnect: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reconnect(Request $request, SocialAccount $account)
    {
        $this->denyUnlessManager();

        if ($account->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // An account whose token died must go through the login flow again.
        if ($account->status === 'expired') {
            return response()->json([
                'error' => 'The access token is no longer valid. Please re-authorize this account.',
                'reauthorize_url' => $this->reauthorizeUrl($account),
            ], 409);
        }

        if ($account->status === 'disconnected' && ! Auth::user()->organization->canAddSocialAccount()) {
            return response()->json(['error' => 'Your plan\'s connected-account limit has been reached.'], 422);
        }

        try {
            $account->update([
                'status' => 'connected',
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'Account reconnected successfully',
                'status' => 'connected',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to reconnect'], 500);
        }
    }

    public function test(Request $request, SocialAccount $account)
    {
        if ($account->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $result = match ($account->platform) {
                'facebook', 'instagram' => $this->testFacebookConnection($account),
                'youtube' => $this->testYoutubeConnection($account),
                'twitter' => $this->testTwitterConnection($account),
                'linkedin' => $this->testLinkedInConnection($account),
                default => ['status' => 'error', 'message' => 'Unknown platform'],
            };

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function testFacebookConnection(SocialAccount $account): array
    {
        $field = $account->platform === 'instagram' ? 'id,username' : 'id,name';
        $response = Http::get(MetaApi::base($account).'/'.$account->platform_account_id, [
            'fields' => $field,
            'access_token' => $account->access_token,
        ]);
        $data = $response->json();

        if (isset($data['error'])) {
            if (MetaApi::isTokenError($data)) {
                $account->markTokenInvalid($data['error']['message'] ?? 'Token invalid');
            }

            return ['status' => 'error', 'message' => $data['error']['message'] ?? 'Connection failed'];
        }

        return ['status' => 'success', 'message' => 'Account is connected and working'];
    }

    /** Re-create the webhook subscription (useful after changing the Meta app configuration). */
    public function resubscribe(Request $request, SocialAccount $account, MetaOAuthService $meta, CredentialResolver $resolver)
    {
        $this->denyUnlessManager();

        $credential = $resolver->forAccount($account);
        if (! $credential) {
            return response()->json(['error' => 'No Meta app is configured for this account.'], 422);
        }

        $ok = match (true) {
            $account->isInstagramLogin() => $meta->subscribeInstagram($credential, $account->platform_account_id, $account->access_token),
            $account->platform === 'facebook' => $meta->subscribePage($credential, $account->platform_account_id, $account->access_token),
            $account->platform === 'instagram' && ! empty($account->metadata['facebook_page_id'])
                => $meta->subscribePage($credential, $account->metadata['facebook_page_id'], $account->access_token),
            default => false,
        };

        if ($ok) {
            $account->update(['webhook_subscribed_at' => now()]);
        }

        return response()->json(
            $ok ? ['message' => 'Webhook subscription refreshed'] : ['error' => 'Meta refused the subscription. Check the app permissions.'],
            $ok ? 200 : 422
        );
    }

    private function testYoutubeConnection(SocialAccount $account): array
    {
        $response = Http::withToken($account->access_token)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet',
                'mine' => 'true',
            ]);

        $data = $response->json();

        if (!$response->successful() || isset($data['error'])) {
            return [
                'status' => 'error',
                'message' => $data['error']['message'] ?? 'YouTube connection failed',
            ];
        }

        return [
            'status' => 'success',
            'message' => 'YouTube channel is connected',
        ];
    }

    private function testTwitterConnection(SocialAccount $account): array
    {
        $response = Http::withToken($account->access_token)
            ->get('https://api.x.com/2/users/me', [
                'user.fields' => 'id,name,username',
            ]);

        $data = $response->json();

        if (!$response->successful() || isset($data['detail'])) {
            return [
                'status' => 'error',
                'message' => $data['detail'] ?? 'X connection failed',
            ];
        }

        return [
            'status' => 'success',
            'message' => 'X account is connected',
        ];
    }

    private function testLinkedInConnection(SocialAccount $account): array
    {
        $response = Http::withToken($account->access_token)
            ->withHeaders([
                'LinkedIn-Version' => '202405',
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->get('https://api.linkedin.com/v2/userinfo');

        $data = $response->json();

        if (!$response->successful() || isset($data['message'])) {
            return [
                'status' => 'error',
                'message' => $data['message'] ?? 'LinkedIn connection failed',
            ];
        }

        return [
            'status' => 'success',
            'message' => 'LinkedIn account is connected',
        ];
    }
}
