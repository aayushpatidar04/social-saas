<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * CSRF `state` for OAuth round-trips. Stored server-side and bound to the user
 * who started the flow, so a callback can only be completed by that same user,
 * for the organization / app they started with.
 */
final class OAuthState
{
    public static function issue(string $platform, int $userId, int $organizationId, int $credentialId, array $extra = []): string
    {
        $state = Str::random(40);
        Cache::put("oauth_state:{$state}", [
            'platform' => $platform,
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'credential_id' => $credentialId,
        ], now()->addMinutes(10));

        return $state;
    }

    /** One-time use. Returns null when missing, expired, for another platform or another user. */
    public static function consume(?string $state, string $platform, int $userId): ?array
    {
        if (! $state) {
            return null;
        }

        $data = Cache::pull("oauth_state:{$state}");

        if (! $data || $data['platform'] !== $platform || (int) $data['user_id'] !== $userId) {
            return null;
        }

        return $data;
    }
}
