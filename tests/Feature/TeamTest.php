<?php

namespace Tests\Feature;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_admin_invites_a_colleague_and_the_link_works_once(): void
    {
        Mail::fake();
        $org = $this->org(['plan' => 'professional']);
        $admin = $this->userIn($org, 'admin');

        $this->actingAs($admin)->post('/settings/team/invite', ['email' => 'Sam@Example.com', 'role' => 'team_member'])
            ->assertRedirect()->assertSessionHas('invite_url');

        $invitation = OrganizationInvitation::firstOrFail();
        $this->assertSame('sam@example.com', $invitation->email);
        $this->assertNotSame(session('invite_url'), $invitation->token_hash);   // only a hash is stored
        $this->assertStringNotContainsString(substr($invitation->token_hash, 0, 10), session('invite_url'));
    }

    public function test_non_admins_cannot_manage_the_team(): void
    {
        $org = $this->org();
        $manager = $this->userIn($org, 'manager');
        $member = $this->userIn($org, 'team_member');

        $this->actingAs($manager)->post('/settings/team/invite', ['email' => 'a@b.test', 'role' => 'viewer'])->assertForbidden();
        $this->actingAs($manager)->delete("/settings/team/{$member->id}")->assertForbidden();
        $this->actingAs($manager)->patch("/settings/team/{$member->id}/role", ['role' => 'admin'])->assertForbidden();
    }

    public function test_user_limit_counts_pending_invitations(): void
    {
        Mail::fake();
        $org = $this->org(['plan' => 'free']);            // free = 2 users
        $admin = $this->userIn($org, 'admin');

        $this->actingAs($admin)->post('/settings/team/invite', ['email' => 'one@x.test', 'role' => 'viewer'])->assertSessionHas('success');
        $this->actingAs($admin)->post('/settings/team/invite', ['email' => 'two@x.test', 'role' => 'viewer'])->assertSessionHas('error');

        $this->assertSame(1, OrganizationInvitation::count());
    }

    public function test_cannot_invite_an_existing_user_or_use_an_unknown_role(): void
    {
        $org = $this->org();
        $admin = $this->userIn($org, 'admin');

        $this->actingAs($admin)->post('/settings/team/invite', ['email' => $admin->email, 'role' => 'viewer'])->assertSessionHasErrors('email');
        $this->actingAs($admin)->post('/settings/team/invite', ['email' => 'ok@x.test', 'role' => 'owner'])->assertSessionHasErrors('role');
    }

    public function test_expired_invitations_are_invalid(): void
    {
        Mail::fake();
        $org = $this->org();
        $this->actingAs($this->userIn($org))->post('/settings/team/invite', ['email' => 'late@x.test', 'role' => 'viewer']);
        $url = session('invite_url');
        OrganizationInvitation::query()->update(['expires_at' => now()->subMinute()]);
        auth()->logout();

        $this->post($url, ['name' => 'Late', 'password' => 'Str0ng-pass-123!', 'password_confirmation' => 'Str0ng-pass-123!'])->assertNotFound();
        $this->assertNull(User::where('email', 'late@x.test')->first());
    }

    public function test_the_last_admin_and_yourself_cannot_be_removed_or_demoted(): void
    {
        $org = $this->org();
        $admin = $this->userIn($org, 'admin');

        $this->actingAs($admin)->delete("/settings/team/{$admin->id}")->assertSessionHas('error');
        $this->actingAs($admin)->patch("/settings/team/{$admin->id}/role", ['role' => 'viewer'])->assertSessionHas('error');
        $this->assertSame('admin', $admin->fresh()->role);

        $second = $this->userIn($org, 'admin');
        $this->actingAs($admin)->patch("/settings/team/{$second->id}/role", ['role' => 'viewer'])->assertSessionHas('success');
        $this->actingAs($admin)->delete("/settings/team/{$second->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($second);
    }

    public function test_admin_cannot_touch_users_of_other_organizations(): void
    {
        $mine = $this->userIn($this->org(), 'admin');
        $foreign = $this->userIn($this->org(), 'team_member');

        $this->actingAs($mine)->delete("/settings/team/{$foreign->id}")->assertNotFound();
        $this->actingAs($mine)->patch("/settings/team/{$foreign->id}/role", ['role' => 'admin'])->assertNotFound();
        $this->assertSame('team_member', $foreign->fresh()->role);
    }

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $status = $this->post('/register', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'p', 'password_confirmation' => 'p'])->status();
        $this->assertContains($status, [404, 405]);
        $this->assertNull(User::where('email', 'x@x.test')->first());
    }
}
