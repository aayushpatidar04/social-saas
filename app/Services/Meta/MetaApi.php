<?php

namespace App\Services\Meta;

use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Services\Credentials\CredentialResolver;
use Illuminate\Http\Client\Response;

/**
 * Small helpers shared by every class that talks to the Meta Graph API.
 *
 * Accounts connected through Facebook Login (Pages and the Instagram accounts
 * linked to them) are served by graph.facebook.com. Instagram accounts connected
 * with "Instagram API with Instagram Login" are served by graph.instagram.com
 * and use a different token, so the host depends on the account.
 */
final class MetaApi
{
    public const DEFAULT_VERSION = 'v25.0';

    public static function version(?PlatformCredential $credential): string
    {
        return $credential?->get('graph_version', self::DEFAULT_VERSION) ?: self::DEFAULT_VERSION;
    }

    public static function facebookBase(?PlatformCredential $credential): string
    {
        return 'https://graph.facebook.com/'.self::version($credential);
    }

    public static function instagramBase(?PlatformCredential $credential): string
    {
        return 'https://graph.instagram.com/'.self::version($credential);
    }

    /** Base URL (host + version, no trailing slash) for calls made on behalf of $account. */
    public static function base(SocialAccount $account): string
    {
        $credential = app(CredentialResolver::class)->forAccount($account);

        return $account->isInstagramLogin()
            ? self::instagramBase($credential)
            : self::facebookBase($credential);
    }

    /** Meta signals an invalid / expired / revoked token with error code 190 (or 102 for sessions). */
    public static function isTokenError(?array $json): bool
    {
        $code = (int) data_get($json, 'error.code');

        return in_array($code, [190, 102], true)
            || data_get($json, 'error.type') === 'OAuthException' && $code === 190;
    }

    public static function errorMessage(Response|array|null $response): string
    {
        $json = $response instanceof Response ? $response->json() : $response;

        return (string) (data_get($json, 'error.message')
            ?? data_get($json, 'error_message')
            ?? ($response instanceof Response ? 'HTTP '.$response->status() : 'Unknown error'));
    }
}
