<?php

namespace App\Services\Credentials;

use App\Models\Organization;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;

class CredentialResolver
{
    public static function providerFor(string $platform): string
    {
        return config("integrations.platform_provider.{$platform}", $platform);
    }

    /**
     * The app to use for an organization: its own app when the super admin allows
     * that and it is configured, otherwise the platform-wide app.
     */
    public function for(string $provider, ?int $organizationId = null): ?PlatformCredential
    {
        if ($organizationId && $this->mayUseOwnApps($organizationId)) {
            $own = PlatformCredential::where('scope_key', (string) $organizationId)
                ->where('provider', $provider)->where('is_active', true)->first();
            if ($own?->isConfigured()) {
                return $own;
            }
        }

        $platform = PlatformCredential::where('scope_key', 'platform')
            ->where('provider', $provider)->where('is_active', true)->first();

        return $platform?->isConfigured() ? $platform : null;
    }

    public function forPlatform(string $platform, ?int $organizationId): ?PlatformCredential
    {
        $cred = $this->for(self::providerFor($platform), $organizationId);

        return $cred && $cred->supports($platform) ? $cred : null;
    }

    /** Prefer the app that issued the account's token (needed for refresh). */
    public function forAccount(SocialAccount $account): ?PlatformCredential
    {
        if ($account->credential_id) {
            $c = PlatformCredential::find($account->credential_id);
            if ($c && $c->is_active && $c->isConfigured()) {
                return $c;
            }
        }

        return $this->forPlatform($account->platform, $account->organization_id);
    }

    /** Like for(), but fails loudly: used by code that cannot proceed without it. */
    public function require(string $provider, ?int $organizationId): PlatformCredential
    {
        return $this->for($provider, $organizationId)
            ?? throw new \RuntimeException("The {$provider} integration is not configured. Ask your administrator to add its credentials.");
    }

    /** Which platforms can this organization connect right now? */
    public function availability(?int $organizationId): array
    {
        $out = [];
        foreach (array_keys(config('integrations.platform_provider')) as $platform) {
            $out[$platform] = $this->forPlatform($platform, $organizationId) !== null;
        }

        return $out;
    }

    private function mayUseOwnApps(int $organizationId): bool
    {
        return (bool) Organization::withoutGlobalScopes()->whereKey($organizationId)->value('allow_custom_apps');
    }
}
