<?php

namespace App\Services\Meta;

use App\Models\Organization;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Models\User;

/**
 * Creates / updates a SocialAccount for an organization, enforcing:
 *   - one external account belongs to exactly one organization
 *   - the organization's plan limit on connected accounts
 */
class SocialAccountConnector
{
    /**
     * @param  array  $attrs  name, handle, access_token, refresh_token, token_expires_at,
     *                        auth_mode, parent_account_id, metadata
     */
    public function connect(
        Organization $org,
        ?User $user,
        string $platform,
        string $externalId,
        array $attrs,
        ?PlatformCredential $credential = null,
    ): SocialAccount {
        $existing = SocialAccount::withoutGlobalScopes()->withTrashed()
            ->where('platform', $platform)
            ->where('platform_account_id', $externalId)
            ->first();

        if ($existing && (int) $existing->organization_id !== (int) $org->id) {
            throw new MetaConnectionException(
                'This '.ucfirst($platform).' account is already connected to another organization.'
            );
        }

        $takesNewSlot = ! $existing || $existing->trashed() || $existing->status === 'disconnected';
        if ($takesNewSlot && ! $org->canAddSocialAccount()) {
            $max = $org->limit('social_accounts');
            throw new MetaConnectionException(
                "Your plan allows {$max} connected social accounts. Disconnect one or ask your administrator to raise the limit."
            );
        }

        $values = [
            'organization_id' => $org->id,
            'platform' => $platform,
            'platform_account_id' => $externalId,
            'platform_account_name' => $attrs['name'] ?? $externalId,
            'platform_account_handle' => $attrs['handle'] ?? '',
            'access_token' => $attrs['access_token'],
            'refresh_token' => $attrs['refresh_token'] ?? null,
            'token_expires_at' => $attrs['token_expires_at'] ?? null,
            'token_refreshed_at' => now(),
            'auth_mode' => $attrs['auth_mode'] ?? null,
            'parent_account_id' => $attrs['parent_account_id'] ?? null,
            'credential_id' => $credential?->id,
            'status' => 'connected',
            'error_message' => null,
            'is_active' => true,
            'metadata' => array_merge($existing?->metadata ?? [], $attrs['metadata'] ?? []),
        ];

        if (isset($attrs['auto_reply_started_at'])) {
            $values['auto_reply_started_at'] = $attrs['auto_reply_started_at'];
        }

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->forceFill($values)->save();

            return $existing->refresh();
        }

        $account = new SocialAccount();
        $account->forceFill($values + ['user_id' => $user?->id])->save();

        return $account;
    }
}
