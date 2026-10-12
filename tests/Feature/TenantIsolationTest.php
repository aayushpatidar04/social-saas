<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\SocialComment;
use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_models_are_scoped_to_the_signed_in_users_organization(): void
    {
        $a = $this->org();
        $b = $this->org();
        $this->account($a);
        $this->account($b);
        $this->account($b, 'youtube');

        $this->actingAs($this->userIn($a));

        $this->assertSame(1, SocialAccount::count());
        $this->assertSame(2, SocialAccount::withoutGlobalScopes()->where('organization_id', $b->id)->count());
    }

    public function test_child_rows_inherit_the_organization_of_their_account(): void
    {
        $org = $this->org();
        $account = $this->account($org);

        $post = SocialPost::create(['platform' => 'facebook', 'social_account_id' => $account->id, 'platform_post_id' => 'p1', 'content' => 'hi', 'posted_at' => now()]);
        $comment = SocialComment::create(['platform' => 'facebook', 'social_post_id' => $post->id, 'social_account_id' => $account->id,
            'platform_comment_id' => 'c1', 'platform_author_id' => 'x', 'author_name' => 'Bob', 'content' => 'hello', 'commented_at' => now()]);

        $this->assertSame($org->id, $post->organization_id);
        $this->assertSame($org->id, $comment->organization_id);
    }

    public function test_user_cannot_touch_another_organizations_account(): void
    {
        $a = $this->org();
        $b = $this->org();
        $foreign = $this->account($b);
        $user = $this->userIn($a);

        $this->actingAs($user)->postJson("/settings/social-accounts/{$foreign->id}/disconnect")->assertNotFound();
        $this->actingAs($user)->postJson("/settings/social-accounts/{$foreign->id}/sync")->assertNotFound();
        $this->assertSame('connected', $foreign->fresh()->status);
    }

    public function test_accounts_page_lists_only_own_accounts_and_never_tokens(): void
    {
        $a = $this->org();
        $b = $this->org();
        $mine = $this->account($a, 'facebook', ['access_token' => 'MY-SECRET-TOKEN']);
        $this->account($b, 'facebook', ['platform_account_name' => 'Other tenant page']);

        $response = $this->actingAs($this->userIn($a))->get('/settings/social-accounts')->assertOk();

        $response->assertInertia(fn ($page) => $page->component('Settings/SocialAccounts')->has('accounts', 1)
            ->where('accounts.0.id', $mine->id));
        $this->assertStringNotContainsString('MY-SECRET-TOKEN', $response->getContent());
        $this->assertStringNotContainsString('Other tenant page', $response->getContent());
    }

    public function test_stored_tokens_are_encrypted_at_rest(): void
    {
        $account = $this->account($this->org(), 'facebook', ['access_token' => 'PLAIN-TOKEN', 'refresh_token' => 'PLAIN-REFRESH']);

        $raw = \DB::table('social_accounts')->where('id', $account->id)->first();
        $this->assertStringNotContainsString('PLAIN', $raw->access_token);
        $this->assertStringNotContainsString('PLAIN', $raw->refresh_token);
        $this->assertSame('PLAIN-TOKEN', $account->fresh()->access_token);
    }

    public function test_one_external_account_cannot_exist_twice(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $org = $this->org();
        $this->account($org, 'facebook', ['platform_account_id' => 'DUP']);
        $this->account($this->org(), 'facebook', ['platform_account_id' => 'DUP']);
    }
}
