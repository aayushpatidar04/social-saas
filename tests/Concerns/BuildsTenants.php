<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Models\User;

trait BuildsTenants
{
    protected function org(array $attrs = []): Organization
    {
        return Organization::factory()->create($attrs);
    }

    protected function userIn(Organization $org, string $role = 'admin'): User
    {
        return User::factory()->create(['organization_id' => $org->id, 'role' => $role]);
    }

    protected function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** A Meta app with both the Facebook and the Instagram pair filled in. */
    protected function metaApp(?Organization $org = null, array $data = []): PlatformCredential
    {
        return PlatformCredential::create([
            'organization_id' => $org?->id,
            'provider' => 'meta',
            'label' => $org ? 'Own app' : 'Platform app',
            'data' => $data + [
                'facebook_app_id' => '1111',
                'facebook_app_secret' => 'fb-secret',
                'instagram_app_id' => '2222',
                'instagram_app_secret' => 'ig-secret',
                'webhook_verify_token' => 'verify-me',
                'graph_version' => 'v25.0',
            ],
        ]);
    }

    protected function account(Organization $org, string $platform = 'facebook', array $attrs = []): SocialAccount
    {
        $owner = User::withoutGlobalScopes()->where('organization_id', $org->id)->first() ?? $this->userIn($org);

        $account = new SocialAccount();
        $account->forceFill($attrs + [
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'platform' => $platform,
            'platform_account_id' => $platform.'-'.uniqid(),
            'platform_account_name' => ucfirst($platform).' account',
            'access_token' => 'token-'.uniqid(),
            'status' => 'connected',
            'is_active' => true,
            'auth_mode' => in_array($platform, ['facebook', 'instagram']) ? 'facebook_login' : 'oauth',
        ])->save();

        return $account;
    }
}
