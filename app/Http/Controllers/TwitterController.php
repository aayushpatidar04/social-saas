<?php

namespace App\Http\Controllers;

use App\Jobs\SyncTwitterComments;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Services\Credentials\CredentialResolver;
use App\Services\Meta\OAuthState;
use App\Services\Meta\SocialAccountConnector;
use App\Models\SocialComment;
use App\Services\TwitterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TwitterController extends Controller
{
    public function login(CredentialResolver $resolver, TwitterService $twitter)
    {
        $user = Auth::user();

        if (! $user->canManageOrganization()) {
            return redirect('/settings/social-accounts')->with('error', 'Only organization admins and managers can connect accounts.');
        }

        $credential = $resolver->forPlatform('twitter', $user->organization_id);
        if (! $credential) {
            return redirect('/settings/social-accounts')->with('error', 'X is not configured yet. Ask your administrator to add the X app credentials.');
        }

        $codeVerifier = $twitter->generateCodeVerifier();
        $state = OAuthState::issue('twitter', $user->id, $user->organization_id, $credential->id, ['code_verifier' => $codeVerifier]);

        return redirect($twitter->getAuthUrl($credential, $state, $twitter->generateCodeChallenge($codeVerifier)));
    }

    public function callback(Request $request, TwitterService $twitter, SocialAccountConnector $connector)
    {
        $state = OAuthState::consume($request->state, 'twitter', Auth::id());

        if ($request->error) {
            return redirect('/settings/social-accounts')
                ->with('error', 'X error: ' . $request->error);
        }

        if (! $state) {
            return redirect('/settings/social-accounts')
                ->with('error', 'Invalid or expired X login session. Please try again.');
        }

        if (!$request->code) {
            return redirect('/settings/social-accounts')
                ->with('error', 'No authorization code received from X.');
        }

        try {
            $credential = PlatformCredential::findOrFail($state['credential_id']);
            $tokenData = $twitter->exchangeCodeForToken($request->code, $state['extra']['code_verifier'], $credential);

            $accessToken = $tokenData['access_token'] ?? null;

            if (!$accessToken) {
                throw new \Exception('No X access token received.');
            }

            $user = $twitter->getCurrentUser($accessToken);
            if (! $user) {
                throw new \Exception('Could not read the X profile.');
            }

            $connector->connect(Auth::user()->organization, Auth::user(), 'twitter', $user['id'], [
                'name' => $user['name'] ?? 'X Account',
                'handle' => $user['username'] ?? null,
                'access_token' => $accessToken,
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 7200),
                'auth_mode' => 'oauth',
                'metadata' => ['user' => $user],
                'auto_reply_started_at' => now(),
            ], $credential);

            return redirect('/settings/social-accounts')
                ->with('success', 'X account connected successfully.');

        } catch (\Exception $e) {
            Log::error('X callback error: ' . $e->getMessage());

            return redirect('/settings/social-accounts')
                ->with('error', $e->getMessage());
        }
    }

    public function sync(Request $request, SocialAccount $account)
    {
        if ($account->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($account->platform !== 'twitter') {
            return response()->json(['error' => 'Invalid X account.'], 422);
        }

        SyncTwitterComments::dispatch($account);

        return response()->json([
            'message' => 'X sync started.',
            'status' => 'processing',
        ]);
    }

    public function reply(Request $request, SocialComment $comment, TwitterService $twitter)
    {
        $request->validate([
            'message' => 'required|string|max:280',
        ]);

        if ($comment->socialAccount->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $response = $twitter->replyToTweet(
            $comment->socialAccount,
            $comment->platform_comment_id,
            $request->message
        );

        return response()->json([
            'message' => 'Reply posted successfully.',
            'data' => $response,
        ]);
    }
}