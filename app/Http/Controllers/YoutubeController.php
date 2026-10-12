<?php

namespace App\Http\Controllers;

use App\Jobs\SyncYoutubeComments;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Services\Credentials\CredentialResolver;
use App\Services\Meta\OAuthState;
use App\Services\Meta\SocialAccountConnector;
use App\Models\SocialComment;
use App\Services\YoutubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function callback(Request $request, YoutubeService $youtube, SocialAccountConnector $connector)
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

            $channel = $youtube->getMyChannel($tokenData['access_token']);

            if (!$channel) {
                throw new \Exception('No YouTube channel found for this Google account.');
            }

            $connector->connect(Auth::user()->organization, Auth::user(), 'youtube', $channel['id'], [
                'name' => $channel['snippet']['title'] ?? 'YouTube Channel',
                'handle' => $channel['snippet']['customUrl'] ?? null,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                'auth_mode' => 'oauth',
                'metadata' => ['channel' => $channel],
                'auto_reply_started_at' => now(),
            ], $credential);

            return redirect('/settings/social-accounts')
                ->with('success', 'YouTube channel connected successfully.');

        } catch (\Exception $e) {
            Log::error('YouTube callback error: ' . $e->getMessage());

            return redirect('/settings/social-accounts')
                ->with('error', $e->getMessage());
        }
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
