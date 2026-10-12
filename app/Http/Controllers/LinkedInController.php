<?php

namespace App\Http\Controllers;

use App\Jobs\SyncLinkedInComments;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Services\Credentials\CredentialResolver;
use App\Services\Meta\OAuthState;
use App\Services\Meta\SocialAccountConnector;
use App\Models\SocialComment;
use App\Services\LinkedInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LinkedInController extends Controller
{
    public function login(CredentialResolver $resolver, LinkedInService $linkedin)
    {
        $user = Auth::user();

        if (! $user->canManageOrganization()) {
            return redirect('/settings/social-accounts')->with('error', 'Only organization admins and managers can connect accounts.');
        }

        $credential = $resolver->forPlatform('linkedin', $user->organization_id);
        if (! $credential) {
            return redirect('/settings/social-accounts')->with('error', 'LinkedIn is not configured yet. Ask your administrator to add the LinkedIn app credentials.');
        }

        $state = OAuthState::issue('linkedin', $user->id, $user->organization_id, $credential->id);

        return redirect($linkedin->getAuthUrl($credential, $state));
    }

    public function callback(Request $request, LinkedInService $linkedin, SocialAccountConnector $connector)
    {
        $state = OAuthState::consume($request->state, 'linkedin', Auth::id());

        if ($request->error) {
            return redirect('/settings/social-accounts')
                ->with('error', 'LinkedIn error: ' . $request->error);
        }

        if (! $state) {
            return redirect('/settings/social-accounts')
                ->with('error', 'Invalid or expired LinkedIn login session. Please try again.');
        }

        if (!$request->code) {
            return redirect('/settings/social-accounts')
                ->with('error', 'No authorization code received from LinkedIn.');
        }

        try {
            $credential = PlatformCredential::findOrFail($state['credential_id']);
            $tokenData = $linkedin->exchangeCodeForToken($request->code, $credential);

            $accessToken = $tokenData['access_token'] ?? null;

            if (!$accessToken) {
                throw new \Exception('No LinkedIn access token received.');
            }

            $profile = $linkedin->getCurrentUser($accessToken);
            $organizations = $linkedin->getManagedOrganizations($accessToken);

            if (empty($organizations)) {
                throw new \Exception('No LinkedIn organization page found. Make sure this LinkedIn user is an admin of a company page.');
            }

            $savedCount = 0;
            $errors = [];

            foreach ($organizations as $organization) {
                try {
                    $connector->connect(Auth::user()->organization, Auth::user(), 'linkedin', $organization['id'], [
                        'name' => $organization['name'] ?? 'LinkedIn Organization',
                        'handle' => $organization['vanityName'] ?? null,
                        'access_token' => $accessToken,
                        'refresh_token' => $tokenData['refresh_token'] ?? null,
                        'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                        'auth_mode' => 'oauth',
                        'metadata' => ['profile' => $profile, 'organization' => $organization],
                        'auto_reply_started_at' => now(),
                    ], $credential);
                    $savedCount++;
                } catch (\RuntimeException $e) {
                    $errors[] = ($organization['name'] ?? $organization['id']).': '.$e->getMessage();
                }
            }

            if ($savedCount === 0) {
                throw new \Exception(implode(' ', $errors));
            }


            return redirect('/settings/social-accounts')
                ->with('success', "LinkedIn connected successfully. {$savedCount} organization(s) saved.");

        } catch (\Exception $e) {
            Log::error('LinkedIn callback error: ' . $e->getMessage());

            return redirect('/settings/social-accounts')
                ->with('error', $e->getMessage());
        }
    }

    public function sync(Request $request, SocialAccount $account)
    {
        if ($account->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($account->platform !== 'linkedin') {
            return response()->json(['error' => 'Invalid LinkedIn account.'], 422);
        }

        SyncLinkedInComments::dispatch($account);

        return response()->json([
            'message' => 'LinkedIn sync started.',
            'status' => 'processing',
        ]);
    }

    public function reply(Request $request, SocialComment $comment, LinkedInService $linkedin)
    {
        $request->validate([
            'message' => 'required|string|max:1250',
        ]);

        if ($comment->socialAccount->organization_id !== Auth::user()->organization_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $response = $linkedin->replyToComment(
            $comment->socialAccount,
            $comment->platform_comment_id,
            $request->message
        );

        return response()->json([
            'message' => 'LinkedIn reply posted successfully.',
            'data' => $response,
        ]);
    }
}