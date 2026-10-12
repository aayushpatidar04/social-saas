<?php

namespace App\Services\Meta;

use App\Models\PlatformCredential;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The two ways to connect Meta accounts:
 *
 *  1. Facebook Login          -> Pages, plus the Instagram accounts linked to them.
 *  2. Instagram Login         -> an Instagram professional account directly, no Facebook Page.
 *     ("Instagram API with Instagram Login" / Business Login for Instagram)
 *
 * Every method takes the PlatformCredential (the Meta app) explicitly, so the same
 * code serves the platform-wide app and an organization's own app.
 */
class MetaOAuthService
{
    private const IG_AUTHORIZE = 'https://www.instagram.com/oauth/authorize';
    private const IG_TOKEN = 'https://api.instagram.com/oauth/access_token';
    private const IG_GRAPH = 'https://graph.instagram.com';

    // ------------------------------------------------------------------ Facebook Login

    public function facebookAuthUrl(PlatformCredential $c, string $state): string
    {
        return 'https://www.facebook.com/'.MetaApi::version($c).'/dialog/oauth?'.http_build_query([
            'client_id' => $c->get('facebook_app_id'),
            'redirect_uri' => $c->redirectUri('facebook'),
            'state' => $state,
            'scope' => $c->get('facebook_scopes'),
            'response_type' => 'code',
        ]);
    }

    /** code -> short-lived user token -> long-lived user token (so Page tokens never expire). */
    public function facebookUserToken(PlatformCredential $c, string $code): array
    {
        $base = MetaApi::facebookBase($c);

        $short = $this->expect(Http::get("$base/oauth/access_token", [
            'client_id' => $c->get('facebook_app_id'),
            'client_secret' => $c->get('facebook_app_secret'),
            'redirect_uri' => $c->redirectUri('facebook'),
            'code' => $code,
        ]), 'Facebook refused the login code');

        $long = $this->expect(Http::get("$base/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $c->get('facebook_app_id'),
            'client_secret' => $c->get('facebook_app_secret'),
            'fb_exchange_token' => $short['access_token'],
        ]), 'Could not extend the Facebook token');

        return [
            'access_token' => $long['access_token'],
            'expires_in' => $long['expires_in'] ?? null,
        ];
    }

    /**
     * Every Page the user manages, with its own token and the linked Instagram
     * professional account (if any). Follows Graph pagination.
     */
    public function facebookPages(PlatformCredential $c, string $userToken): array
    {
        $url = MetaApi::facebookBase($c).'/me/accounts';
        $params = [
            'fields' => 'id,name,access_token,category,picture.width(100){url},'
                .'instagram_business_account{id,username,name,profile_picture_url}',
            'limit' => 100,
            'access_token' => $userToken,
        ];

        $pages = [];
        for ($i = 0; $i < 20 && $url; $i++) {
            $json = $this->expect(Http::get($url, $params), 'Could not load your Facebook Pages');
            foreach ($json['data'] ?? [] as $p) {
                if (empty($p['access_token'])) {
                    continue; // user lacks a task on this page; no token issued
                }
                $pages[] = [
                    'id' => (string) $p['id'],
                    'name' => $p['name'] ?? 'Facebook Page',
                    'category' => $p['category'] ?? null,
                    'picture' => data_get($p, 'picture.data.url'),
                    'access_token' => $p['access_token'],
                    'instagram' => isset($p['instagram_business_account']['id']) ? [
                        'id' => (string) $p['instagram_business_account']['id'],
                        'username' => $p['instagram_business_account']['username'] ?? null,
                        'name' => $p['instagram_business_account']['name'] ?? null,
                        'picture' => $p['instagram_business_account']['profile_picture_url'] ?? null,
                    ] : null,
                ];
            }
            $url = data_get($json, 'paging.next');
            $params = []; // `next` already carries the query string
        }

        return $pages;
    }

    /** Make Meta deliver this Page's feed (comments) events to our webhook. */
    public function subscribePage(PlatformCredential $c, string $pageId, string $pageToken): bool
    {
        $r = Http::asForm()->post(MetaApi::facebookBase($c)."/{$pageId}/subscribed_apps", [
            'subscribed_fields' => 'feed',
            'access_token' => $pageToken,
        ]);

        return $r->successful() && (bool) $r->json('success');
    }

    // ------------------------------------------------------------------ Instagram Login

    public function instagramAuthUrl(PlatformCredential $c, string $state): string
    {
        return self::IG_AUTHORIZE.'?'.http_build_query([
            'client_id' => $c->get('instagram_app_id'),
            'redirect_uri' => $c->redirectUri('instagram'),
            'response_type' => 'code',
            'scope' => $c->get('instagram_scopes'),
            'state' => $state,
            // Always ask which Instagram account to use, otherwise a second account
            // cannot be added from a browser that is already logged in to the first.
            'force_reauth' => 'true',
        ]);
    }

    /** code -> short-lived token -> long-lived (60 day) token. */
    public function instagramTokens(PlatformCredential $c, string $code): array
    {
        $short = $this->expect(Http::asForm()->post(self::IG_TOKEN, [
            'client_id' => $c->get('instagram_app_id'),
            'client_secret' => $c->get('instagram_app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $c->redirectUri('instagram'),
            'code' => $code,
        ]), 'Instagram refused the login code');

        // Meta returns either a flat object or {"data":[{...}]} depending on app version.
        $short = $short['data'][0] ?? $short;
        if (empty($short['access_token'])) {
            throw new MetaConnectionException('Instagram did not return an access token.');
        }

        $long = $this->expect(Http::get(self::IG_GRAPH.'/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $c->get('instagram_app_secret'),
            'access_token' => $short['access_token'],
        ]), 'Could not extend the Instagram token');

        return [
            'access_token' => $long['access_token'],
            'expires_in' => (int) ($long['expires_in'] ?? 5184000), // 60 days
            'user_id' => isset($short['user_id']) ? (string) $short['user_id'] : null,
            'permissions' => $short['permissions'] ?? null,
        ];
    }

    /** The professional account behind the token. `user_id` is the id used in webhooks. */
    public function instagramProfile(PlatformCredential $c, string $token): array
    {
        $json = $this->expect(Http::get(MetaApi::instagramBase($c).'/me', [
            'fields' => 'user_id,username,name,account_type,profile_picture_url',
            'access_token' => $token,
        ]), 'Could not read the Instagram account');

        $id = (string) ($json['user_id'] ?? $json['id'] ?? '');
        if ($id === '') {
            throw new MetaConnectionException('Instagram did not return an account id.');
        }

        $type = $json['account_type'] ?? null;
        if ($type !== null && ! in_array(strtoupper($type), ['BUSINESS', 'CREATOR', 'MEDIA_CREATOR'], true)) {
            throw new MetaConnectionException(
                'This Instagram account is a personal account. Switch it to a Business or Creator (professional) account in the Instagram app and try again.'
            );
        }

        return [
            'id' => $id,
            'app_scoped_id' => isset($json['id']) ? (string) $json['id'] : null,
            'username' => $json['username'] ?? null,
            'name' => $json['name'] ?? ($json['username'] ?? null),
            'account_type' => $type,
            'picture' => $json['profile_picture_url'] ?? null,
        ];
    }

    /** Per-account webhook subscription for comments. */
    public function subscribeInstagram(PlatformCredential $c, string $igUserId, string $token): bool
    {
        $r = Http::asForm()->post(MetaApi::instagramBase($c)."/{$igUserId}/subscribed_apps", [
            'subscribed_fields' => 'comments',
            'access_token' => $token,
        ]);

        return $r->successful() && (bool) $r->json('success');
    }

    /** Extend a still-valid long-lived token by another 60 days (must be >= 24h old). */
    public function refreshInstagramToken(string $token): array
    {
        $json = $this->expect(Http::get(self::IG_GRAPH.'/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ]), 'Instagram token refresh failed');

        return [
            'access_token' => $json['access_token'],
            'expires_in' => (int) ($json['expires_in'] ?? 5184000),
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function expect(Response $r, string $context): array
    {
        $json = $r->json() ?? [];

        if ($r->failed() || isset($json['error']) || isset($json['error_type'])) {
            throw new MetaConnectionException($context.': '.MetaApi::errorMessage($json ?: $r));
        }

        return $json;
    }
}
