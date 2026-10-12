<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\Credentials\CredentialResolver;
use App\Services\Meta\MetaConnectionException;
use App\Services\Meta\MetaOAuthService;
use Illuminate\Console\Command;

/**
 * Instagram Login tokens last 60 days and can only be refreshed while still valid
 * (and once they are at least 24h old). Run daily; refreshes anything expiring
 * within the next 14 days. Facebook Page tokens obtained from a long-lived user
 * token do not expire and need no refresh.
 */
class RefreshMetaTokens extends Command
{
    protected $signature = 'meta:refresh-tokens {--days=14 : Refresh tokens expiring within this many days}';

    protected $description = 'Refresh expiring Instagram Login access tokens';

    public function handle(MetaOAuthService $meta, CredentialResolver $resolver): int
    {
        $accounts = SocialAccount::withoutGlobalScopes()
            ->where('platform', 'instagram')
            ->where('auth_mode', 'instagram_login')
            ->where('is_active', true)
            ->where('status', '!=', 'disconnected')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<', now()->addDays((int) $this->option('days')))
            ->where(fn ($q) => $q->whereNull('token_refreshed_at')->orWhere('token_refreshed_at', '<', now()->subDay()))
            ->get();

        $ok = $failed = 0;

        foreach ($accounts as $account) {
            try {
                $new = $meta->refreshInstagramToken($account->access_token);

                $account->forceFill([
                    'access_token' => $new['access_token'],
                    'token_expires_at' => now()->addSeconds($new['expires_in']),
                    'token_refreshed_at' => now(),
                    'status' => 'connected',
                    'error_message' => null,
                ])->save();
                $ok++;
            } catch (MetaConnectionException $e) {
                $failed++;
                // Already past expiry => it can no longer be refreshed, the user must log in again.
                if ($account->token_expires_at->isPast()) {
                    $account->markTokenInvalid('Instagram access token expired. Please reconnect this account.');
                } else {
                    $account->forceFill(['error_message' => 'Token refresh failed: '.$e->getMessage()])->save();
                }
                $this->warn("Account {$account->id}: {$e->getMessage()}");
            }
        }

        $this->info("Instagram tokens refreshed: {$ok}, failed: {$failed}");

        return self::SUCCESS;
    }
}
