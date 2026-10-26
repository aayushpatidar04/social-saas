<?php

namespace App\Http\Controllers;

use App\Jobs\SyncYoutubeComments;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Services\Credentials\CredentialResolver;
use App\Services\Meta\MetaConnectionException;
use App\Services\Meta\OAuthState;
use App\Services\Meta\SocialAccountConnector;
use App\Models\SocialComment;
use App\Services\YoutubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class YoutubeController extends Controller
{
    public function login(CredentialResolver $resolver, YoutubeService $youtube)
    {
        $user = Auth::user();

        if (! $user->canManageOrganization()) {
            return redirect('/settings/social-accounts')->with('error', 'Only organization admins and managers can connect accounts.');
        }

        $credential = $resolver->forPlatform('youtube', $user->organization_id);
        if (! $credential) {
            return redirect('/settings/social-accounts')->with('error', 'YouTube is not configured yet. Ask your administrator to add the Google credentials.');
        }

        $state = OAuthState::issue('youtube', $user->id, $user->organization_id, $credential->id);

        return redirect($youtube->authUrl($credential, $state));
    }

    public function callback(Request $request, YoutubeService $youtube)
    {
        $state = OAuthState::consume($request->state, 'youtube', Auth::id());

        if ($request->error) {
            return redirect('/settings/social-accounts')
                ->with('error', 'YouTube error: ' . $request->error);
        }

        if (! $state) {
            return redirect('/settings/social-accounts')
                ->with('error', 'Invalid or expired YouTube login session. Please try again.');
        }

        if (!$request->code) {
            return redirect('/settings/social-accounts')
                ->with('error', 'No authorization code received from YouTube.');
        }

        try {
            $credential = PlatformCredential::findOrFail($state['credential_id']);
            $tokenData = $youtube->exchangeCodeForToken($request->code, $credential);

            if (empty($tokenData['access_token'])) {
                throw new \Exception('No YouTube access token received.');
            }

            $channels = $youtube->getMyChannels($tokenData['access_token']);

            if (! $channels) {
                throw new \Exception('No YouTube channel found for this Google account.');
            }

            $pendingId = Str::random(40);
            Cache::put("youtube_pending:{$pendingId}", Crypt::encryptString(json_encode([
                'user_id' => Auth::id(),
                'organization_id' => Auth::user()->organization_id,
                'credential_id' => $credential->id,
                'token_data' => $tokenData,
                'channels' => $channels,
            ])), now()->addMinutes((int) config('integrations.pending_selection_ttl', 15)));

            return redirect()->route('auth.youtube.select', ['pending' => $pendingId]);
        } catch (\Exception $e) {
            Log::error('YouTube callback error: ' . $e->getMessage());

            return redirect('/settings/social-accounts')
                ->with('error', $e->getMessage());
        }
    }

    public function select(Request $request)
    {
        $pending = $this->loadPending($request->query('pending'));
        if (! $pending) {
            return redirect('/settings/social-accounts')
                ->with('error', 'The YouTube channel selection expired. Please connect YouTube again.');
        }

        $channelIds = collect($pending['channels'])->pluck('id');
        $known = SocialAccount::withoutGlobalScopes()
            ->where('platform', 'youtube')
            ->whereIn('platform_account_id', $channelIds)
            ->get(['platform_account_id', 'organization_id', 'status'])
            ->keyBy('platform_account_id');

        $organizationId = Auth::user()->organization_id;

        return Inertia::render('Settings/SelectYoutubeChannels', [
            'pending' => $request->query('pending'),
            'channels' => collect($pending['channels'])->map(function ($channel) use ($known, $organizationId) {
                $account = $known->get($channel['id']);
                $state = ! $account ? 'available'
                    : ((int) $account->organization_id !== (int) $organizationId ? 'other_organization'
                        : ($account->status === 'disconnected' ? 'available' : 'connected'));

                return [
                    'id' => $channel['id'],
                    'name' => $channel['snippet']['title'] ?? 'YouTube Channel',
                    'handle' => $channel['snippet']['customUrl'] ?? null,
                    'picture' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
                    'state' => $state,
                ];
            })->values(),
            'limits' => [
                'used' => Auth::user()->organization->socialAccounts()->where('status', '!=', 'disconnected')->count(),
                'max' => Auth::user()->organization->limit('social_accounts'),
            ],
        ]);
    }

    public function store(Request $request, SocialAccountConnector $connector)
    {
        $data = $request->validate([
            'pending' => ['required', 'string'],
            'channel_ids' => ['required', 'array', 'min:1'],
            'channel_ids.*' => ['required', 'string'],
        ]);

        $pending = $this->loadPending($data['pending']);
        if (! $pending) {
            return redirect('/settings/social-accounts')
                ->with('error', 'The YouTube channel selection expired. Please connect YouTube again.');
        }

        $user = Auth::user();
        $credential = PlatformCredential::find($pending['credential_id']);
        if (! $credential) {
            Cache::forget("youtube_pending:{$data['pending']}");

            return redirect('/settings/social-accounts')
                ->with('error', 'The Google app used for this login no longer exists.');
        }

        $channels = collect($pending['channels'])->keyBy('id');
        $tokenData = $pending['token_data'];
        $connected = [];
        $errors = [];

        foreach (array_unique($data['channel_ids']) as $channelId) {
            $channel = $channels->get($channelId);
            if (! $channel) {
                continue;
            }

            try {
                $connected[] = $connector->connect($user->organization, $user, 'youtube', $channel['id'], [
                    'name' => $channel['snippet']['title'] ?? 'YouTube Channel',
                    'handle' => $channel['snippet']['customUrl'] ?? null,
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                    'auth_mode' => 'oauth',
                    'metadata' => ['channel' => $channel],
                    'auto_reply_started_at' => now(),
                ], $credential);
            } catch (MetaConnectionException $e) {
                $errors[] = ($channel['snippet']['title'] ?? 'YouTube Channel').': '.$e->getMessage();
            }
        }

        Cache::forget("youtube_pending:{$data['pending']}");

        $flash = [];
        if ($connected) {
            $flash['success'] = 'Connected '.count($connected).' YouTube channel'.(count($connected) === 1 ? '' : 's').'.';
        }
        if ($errors) {
            $flash['error'] = implode(' ', $errors);
        }

        return redirect('/settings/social-accounts')
            ->with($flash ?: ['error' => 'Nothing was selected.']);
    }

    private function loadPending(?string $id): ?array
    {
        if (! $id) {
            return null;
        }

        $blob = Cache::get("youtube_pending:{$id}");
        if (! $blob) {
            return null;
        }

        $data = json_decode(Crypt::decryptString($blob), true);

        return ($data['user_id'] ?? null) === Auth::id()
            && ($data['organization_id'] ?? null) === Auth::user()->organization_id
            ? $data : null;
    }

    public function sync(Request $request, SocialAccount $account)
    {
        if ($account->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($account->platform !== 'youtube') {
            return response()->json(['error' => 'Invalid YouTube account.'], 422);
        }

        SyncYoutubeComments::dispatch($account);

        return response()->json([
            'message' => 'YouTube sync started.',
            'status' => 'processing',
        ]);
    }

    /**
     * Subscribe to real-time comment notifications via PubSubHubbub.
     * URL: POST /youtube/subscribe/{account}
     */
    public function subscribe(Request $request, SocialAccount $account, YoutubeService $youtube)
    {
        if ($account->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($account->platform !== 'youtube') {
            return response()->json(['error' => 'Invalid YouTube account.'], 422);
        }

        $result = $youtube->subscribeToVideoNotifications($account);

        return response()->json([
            'message' => 'YouTube webhook subscription completed.',
            'subscribed' => $result['success'],
            'failed' => $result['failed'],
        ]);
    }

    /**
     * Unsubscribe from real-time notifications.
     * URL: POST /youtube/unsubscribe/{account}
     */
    public function unsubscribe(Request $request, SocialAccount $account, YoutubeService $youtube)
    {
        if ($account->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($account->platform !== 'youtube') {
            return response()->json(['error' => 'Invalid YouTube account.'], 422);
        }

        $youtube->unsubscribeFromVideoNotifications($account);

        return response()->json([
            'message' => 'YouTube webhook unsubscribed.',
        ]);
    }

    public function reply(Request $request, SocialComment $comment, YoutubeService $youtube)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        if ($comment->socialAccount->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $parentPlatformId = $comment->parent_id
            ? SocialComment::find($comment->parent_id)?->platform_comment_id
            : $comment->platform_comment_id;

        $response = $youtube->replyToComment(
            $comment->socialAccount,
            $parentPlatformId,
            $request->message
        );

        return response()->json([
            'message' => 'Reply posted successfully.',
            'data' => $response,
        ]);
    }
}
