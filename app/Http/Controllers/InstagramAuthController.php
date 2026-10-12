<?php

namespace App\Http\Controllers;

use App\Jobs\SyncInstagramComments;
use App\Models\PlatformCredential;
use App\Services\Credentials\CredentialResolver;
use App\Services\Meta\MetaConnectionException;
use App\Services\Meta\MetaOAuthService;
use App\Services\Meta\OAuthState;
use App\Services\Meta\SocialAccountConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Connect an Instagram professional account directly with
 * "Instagram API with Instagram Login" - no Facebook Page or Facebook account needed.
 */
class InstagramAuthController extends Controller
{
    public function login(CredentialResolver $resolver, MetaOAuthService $meta)
    {
        $user = Auth::user();

        if (! $user->canManageOrganization()) {
            return $this->back('error', 'Only organization admins and managers can connect accounts.');
        }

        $credential = $resolver->forPlatform('instagram', $user->organization_id);
        if (! $credential) {
            return $this->back('error', 'Instagram login is not configured yet. Ask your administrator to add the Instagram App ID and secret.');
        }

        $state = OAuthState::issue('instagram', $user->id, $user->organization_id, $credential->id);

        return redirect()->away($meta->instagramAuthUrl($credential, $state));
    }

    public function callback(Request $request, MetaOAuthService $meta, SocialAccountConnector $connector)
    {
        $user = Auth::user();
        $state = OAuthState::consume($request->query('state'), 'instagram', $user->id);

        if ($request->filled('error') || $request->filled('error_reason')) {
            return $this->back('error', 'Instagram login was cancelled: '.($request->query('error_description') ?: $request->query('error_reason') ?: $request->query('error')));
        }

        if (! $state || ! $request->filled('code')) {
            return $this->back('error', 'The Instagram login session expired. Please try again.');
        }

        $credential = PlatformCredential::find($state['credential_id']);
        if (! $credential) {
            return $this->back('error', 'The Meta app used for this login no longer exists.');
        }

        try {
            // Instagram appends "#_" to the code.
            $tokens = $meta->instagramTokens($credential, rtrim($request->query('code'), '#_'));
            $profile = $meta->instagramProfile($credential, $tokens['access_token']);

            $account = $connector->connect($user->organization, $user, 'instagram', $profile['id'], [
                'name' => $profile['username'] ? '@'.$profile['username'] : ($profile['name'] ?? 'Instagram '.$profile['id']),
                'handle' => $profile['username'] ? '@'.$profile['username'] : '',
                'access_token' => $tokens['access_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'auth_mode' => 'instagram_login',
                'parent_account_id' => null,
                'metadata' => array_filter([
                    'app_scoped_id' => $profile['app_scoped_id'],
                    'account_type' => $profile['account_type'],
                    'picture' => $profile['picture'],
                    'permissions' => $tokens['permissions'],
                ]),
            ], $credential);
        } catch (MetaConnectionException $e) {
            return $this->back('error', $e->getMessage());
        }

        $subscribed = $meta->subscribeInstagram($credential, $profile['id'], $tokens['access_token']);
        $account->update(['webhook_subscribed_at' => $subscribed ? now() : null]);

        try {
            SyncInstagramComments::dispatch($account);
        } catch (\Throwable $e) {
            Log::warning('Initial Instagram sync could not be queued', ['account' => $account->id, 'error' => $e->getMessage()]);
        }

        return $subscribed
            ? $this->back('success', "Connected {$account->platform_account_name}.")
            : $this->back('success', "Connected {$account->platform_account_name}, but Meta did not accept the webhook subscription. Comments will sync on schedule; use \"Refresh webhook\" to retry.");
    }

    private function back(string $type, string $message)
    {
        return redirect()->route('settings.social-accounts.index')->with($type, $message);
    }
}
