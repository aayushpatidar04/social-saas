<?php

namespace Tests\Feature;

use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_only_super_admins_can_open_the_admin_area(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs($this->userIn($this->org()))->get('/admin')->assertForbidden();
        $this->actingAs($this->superAdmin())->get('/admin')->assertOk();
    }

    public function test_super_admin_is_sent_to_admin_area_instead_of_the_tenant_dashboard(): void
    {
        $this->actingAs($this->superAdmin())->get('/dashboard')->assertRedirect('/admin');
    }

    public function test_super_admin_creates_an_organization_and_invites_its_first_admin(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->superAdmin())->post('/admin/organizations', [
            'name' => 'Acme Retail', 'plan' => 'starter', 'max_social_accounts' => 3, 'allow_custom_apps' => true,
            'admin_name' => 'Ana Admin', 'admin_email' => 'ana@acme.test',
        ]);

        $org = Organization::where('name', 'Acme Retail')->firstOrFail();
        $response->assertRedirect("/admin/organizations/{$org->id}")->assertSessionHas('invite_url');

        $this->assertSame('acme-retail', $org->slug);
        $this->assertSame(3, $org->limit('social_accounts'));
        $this->assertSame(5, $org->limit('users'));            // plan default
        $this->assertTrue($org->allow_custom_apps);
        $this->assertDatabaseHas('organization_invitations', ['organization_id' => $org->id, 'email' => 'ana@acme.test', 'role' => 'admin']);
        Mail::assertSent(OrganizationInvitationMail::class, fn ($m) => $m->hasTo('ana@acme.test'));
    }

    public function test_organization_creation_validates_input(): void
    {
        $existing = $this->userIn($this->org());

        $this->actingAs($this->superAdmin())->post('/admin/organizations', [
            'name' => '', 'plan' => 'platinum', 'admin_name' => 'x', 'admin_email' => $existing->email,
        ])->assertSessionHasErrors(['name', 'plan', 'admin_email']);
    }

    public function test_tenant_users_cannot_create_organizations_or_change_plans(): void
    {
        $org = $this->org(['plan' => 'free']);
        $admin = $this->userIn($org, 'admin');

        $this->actingAs($admin)->post('/admin/organizations', ['name' => 'X'])->assertForbidden();
        // the old self-service plan switch is gone: the settings endpoint ignores plan/limits
        $this->actingAs($admin)->patch('/settings/organization', ['name' => 'Renamed', 'plan' => 'enterprise', 'max_users' => 999])->assertRedirect();
        $org->refresh();
        $this->assertSame('Renamed', $org->name);
        $this->assertSame('free', $org->plan);
        $this->assertNull($org->max_users);
    }

    public function test_suspending_an_organization_blocks_its_users_until_reactivated(): void
    {
        $org = $this->org();
        $user = $this->userIn($org);
        $admin = $this->superAdmin();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->actingAs($admin)->post("/admin/organizations/{$org->id}/suspend", ['reason' => 'Unpaid invoice'])->assertRedirect();
        $this->assertNotNull($org->fresh()->suspended_at);

        // a real request loads the user fresh; don't reuse the relation cached on the first request
        $blocked = $this->actingAs($user->fresh())->get('/dashboard')->assertForbidden();
        $blocked->assertInertia(fn ($p) => $p->component('Suspended')->where('reason', 'Unpaid invoice'));

        $this->actingAs($admin)->post("/admin/organizations/{$org->id}/activate")->assertRedirect();
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_expired_plans_are_blocked(): void
    {
        $org = $this->org(['plan_expires_at' => now()->subDay()]);

        $this->actingAs($this->userIn($org))->get('/dashboard')->assertForbidden();
    }

    public function test_sync_cron_skips_suspended_organizations(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $ok = $this->org();
        $bad = $this->org()->forceFill(['suspended_at' => now()]);
        $bad->save();
        $this->account($ok, 'facebook');
        $this->account($bad, 'facebook');

        $this->artisan('sync:social-cron')->assertSuccessful();

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncSocialCommentsJob::class, 1);
    }

    public function test_impersonation_shows_the_tenant_view_and_can_be_left(): void
    {
        $org = $this->org();
        $other = $this->org();
        $this->account($org);
        $this->account($other);
        $target = $this->userIn($org);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post("/admin/organizations/{$org->id}/impersonate/{$target->id}")->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($target);
        $this->assertSame(1, SocialAccount::count());                 // scoped to the tenant while impersonating
        $this->get('/admin')->assertForbidden();                      // admin area closed meanwhile

        $this->post('/impersonation/leave')->assertRedirect("/admin/organizations/{$org->id}");
        $this->assertAuthenticatedAs($admin);
        $this->assertSame(2, SocialAccount::count());
    }

    public function test_impersonation_leave_requires_an_active_impersonation(): void
    {
        $this->actingAs($this->userIn($this->org()))->post('/impersonation/leave')->assertForbidden();
    }

    public function test_cannot_impersonate_a_user_of_a_different_organization_or_a_super_admin(): void
    {
        $a = $this->org();
        $b = $this->org();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post("/admin/organizations/{$a->id}/impersonate/{$this->userIn($b)->id}")->assertNotFound();
        $this->actingAs($admin)->post("/admin/organizations/{$a->id}/impersonate/{$this->superAdmin()->id}")->assertNotFound();
    }

    public function test_archiving_requires_typing_the_slug(): void
    {
        $org = $this->org();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->delete("/admin/organizations/{$org->id}", ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
        $this->assertNotNull($org->fresh());

        $this->actingAs($admin)->delete("/admin/organizations/{$org->id}", ['confirm' => $org->slug])->assertRedirect('/admin/organizations');
        $this->assertSoftDeleted($org);
    }

    public function test_invitation_accept_flow(): void
    {
        Mail::fake();
        $org = $this->org();
        $this->actingAs($this->superAdmin())->post("/admin/organizations/{$org->id}/invite", ['email' => 'new@acme.test', 'role' => 'manager']);

        $url = session('invite_url');
        $this->assertNotEmpty($url);
        auth()->logout();

        $this->get($url)->assertOk()->assertInertia(fn ($p) => $p->component('Auth/AcceptInvitation')->where('email', 'new@acme.test'));

        $this->post($url, ['name' => 'New Person', 'password' => 'Str0ng-pass-123!', 'password_confirmation' => 'Str0ng-pass-123!'])
            ->assertRedirect('/dashboard');

        $user = \App\Models\User::where('email', 'new@acme.test')->firstOrFail();
        $this->assertSame($org->id, $user->organization_id);
        $this->assertSame('manager', $user->role);
        $this->assertFalse((bool) $user->is_super_admin);
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(OrganizationInvitation::first()->accepted_at);

        // single use
        auth()->logout();
        $this->get($url)->assertInertia(fn ($p) => $p->component('Auth/InvitationInvalid'));
    }
}
