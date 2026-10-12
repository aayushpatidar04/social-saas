<?php

namespace Tests\Feature;

use App\Models\OrganizationInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** Every new screen renders for realistic data, with the props the Vue page reads. */
class PagesRenderTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_every_super_admin_screen_renders(): void
    {
        $admin = $this->superAdmin();
        $org = $this->org(['allow_custom_apps' => true, 'max_users' => 7]);
        $user = $this->userIn($org);
        $this->account($org, 'facebook');
        $this->account($org, 'instagram', ['auth_mode' => 'instagram_login', 'token_expires_at' => now()->addDays(30)]);
        $this->metaApp($org);
        $this->metaApp();
        OrganizationInvitation::create(['organization_id' => $org->id, 'email' => 'x@y.test', 'role' => 'viewer', 'token_hash' => 'h', 'expires_at' => now()->addDay()]);

        $this->actingAs($admin);

        $this->get('/admin')->assertOk()->assertInertia(fn ($p) => $p->component('Admin/Dashboard')
            ->where('stats.organizations', 1)->where('stats.social_accounts', 2)->has('providers', 6));

        $this->get('/admin/organizations')->assertOk()->assertInertia(fn ($p) => $p->component('Admin/Organizations/Index')
            ->has('organizations.data', 1)->where('organizations.data.0.users_count', 1)->where('organizations.data.0.max_users_effective', 7));
        $this->get('/admin/organizations?search=nomatch')->assertOk()->assertInertia(fn ($p) => $p->has('organizations.data', 0));
        $this->get('/admin/organizations?status=suspended')->assertOk();
        $this->get('/admin/organizations/create')->assertOk()->assertInertia(fn ($p) => $p->component('Admin/Organizations/Create')->has('plan_defaults.free'));

        $this->get("/admin/organizations/{$org->id}")->assertOk()->assertInertia(fn ($p) => $p->component('Admin/Organizations/Show')
            ->where('organization.id', $org->id)->has('users', 1)->has('accounts', 2)->has('invitations', 1)->has('own_apps', 1)
            ->where('users.0.id', $user->id));

        $this->get('/admin/credentials')->assertOk()->assertInertia(fn ($p) => $p->component('Admin/Credentials/Index')
            ->where('providers.0.key', 'meta')
            ->where('providers.0.credential.configured', true)
            ->has('providers.0.credential.webhook_url')
            ->has('providers.0.callback_urls.instagram'));
    }

    public function test_every_organization_screen_renders(): void
    {
        $org = $this->org(['allow_custom_apps' => true]);
        $admin = $this->userIn($org, 'admin');
        $this->metaApp();
        $parent = $this->account($org, 'facebook');
        $this->account($org, 'instagram', ['parent_account_id' => $parent->id]);
        $this->account($org, 'instagram', ['auth_mode' => 'instagram_login', 'status' => 'expired']);
        OrganizationInvitation::create(['organization_id' => $org->id, 'email' => 'x@y.test', 'role' => 'viewer', 'token_hash' => 'h', 'expires_at' => now()->addDay()]);

        $this->actingAs($admin);

        $this->get('/settings/social-accounts')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/SocialAccounts')
            ->has('accounts', 3)
            ->where('availability.instagram', true)->where('availability.youtube', false)
            ->where('can_manage', true)->where('limits.used', 3)
            ->where('connect_urls.instagram', route('auth.instagram'))
            ->where('accounts.2.reconnect_url', route('auth.instagram')));   // expired -> re-authorize through the login flow

        $this->get('/settings/team')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/Team')
            ->has('teamMembers', 1)->has('invitations', 1)->where('canManage', true));
        $this->get('/settings/organization')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/Organization')
            ->where('usage.social_accounts.used', 3)->where('canEdit', true));
        $this->get('/settings/integrations')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/Integrations')
            ->where('enabled', true)->has('providers', 5));                  // ollama is infrastructure: not offered
    }

    public function test_integrations_screen_explains_when_custom_apps_are_not_enabled(): void
    {
        $this->actingAs($this->userIn($this->org(['allow_custom_apps' => false])))->get('/settings/integrations')
            ->assertOk()->assertInertia(fn ($p) => $p->where('enabled', false));
    }

    public function test_shared_props_carry_flash_and_organization_for_the_layouts(): void
    {
        $org = $this->org(['allow_custom_apps' => true]);

        $this->actingAs($this->userIn($org))->withSession(['success' => 'Hello', 'invite_url' => 'https://x/y'])->get('/dashboard')
            ->assertInertia(fn ($p) => $p->where('flash.success', 'Hello')->where('flash.invite_url', 'https://x/y')
                ->where('organization.allow_custom_apps', true)->where('impersonating', false));
    }

    public function test_viewers_see_read_only_accounts_and_cannot_connect(): void
    {
        $org = $this->org();
        $this->actingAs($this->userIn($org, 'viewer'))->get('/settings/social-accounts')
            ->assertInertia(fn ($p) => $p->where('can_manage', false));
    }

    public function test_login_page_no_longer_prefills_demo_credentials(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('admin@example.com', $html);
    }
}
