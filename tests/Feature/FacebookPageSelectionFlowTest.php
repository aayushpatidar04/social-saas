<?php

namespace Tests\Feature;

use App\Jobs\SyncFacebookComments;
use App\Jobs\SyncInstagramComments;
use App\Models\Organization;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Meta\OAuthState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** Facebook Login -> pick which Pages / linked Instagram accounts to connect. */
class FacebookPageSelectionFlowTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private Organization $org;
    private User $admin;
    private PlatformCredential $metaCred;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->org = $this->org();
        $this->admin = $this->userIn($this->org, 'admin');
        $this->metaCred = $this->metaApp();
    }

    private function fakeFacebook(?array $pages = null, bool $subscribe = true): void
    {
        // a fresh fake each time: stubs registered earlier would otherwise win
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();

        $pages ??= [
            ['id' => 'PAGE1', 'name' => 'Acme Shop', 'category' => 'Retail', 'access_token' => 'PAGE1-TOKEN',
                'picture' => ['data' => ['url' => 'https://img/p1.jpg']],
                'instagram_business_account' => ['id' => 'IG1', 'username' => 'acme_shop', 'name' => 'Acme']],
            ['id' => 'PAGE2', 'name' => 'Acme Cafe', 'category' => 'Cafe', 'access_token' => 'PAGE2-TOKEN'],
            ['id' => 'PAGE3', 'name' => 'No task access', 'category' => 'X'],   // Meta issues no token: must be skipped
        ];

        // /me/accounts is paged: put the last page on a second response
        $first = array_slice($pages, 0, 2);
        $second = array_slice($pages, 2);

        Http::fake([
            'graph.facebook.com/v25.0/oauth/access_token*' => Http::sequence()
                ->push(['access_token' => 'SHORT-USER-TOKEN', 'expires_in' => 3600])
                ->push(['access_token' => 'LONG-USER-TOKEN', 'expires_in' => 5183999]),
            'graph.facebook.com/v25.0/me/accounts*' => Http::sequence()
                ->push(['data' => $first, 'paging' => ['next' => 'https://graph.facebook.com/v25.0/me/accounts?after=CURSOR']])
                ->push(['data' => $second]),
            'graph.facebook.com/v25.0/*/subscribed_apps' => Http::response(['success' => $subscribe]),
        ]);
    }

    /** Runs login -> callback and returns the pending id from the redirect. */
    private function loginAndGetPending(?User $as = null): string
    {
        $as ??= $this->admin;
        $state = OAuthState::issue('facebook', $as->id, $as->organization_id, $this->metaCred->id);

        $response = $this->actingAs($as)->get('/auth/facebook/callback?code=FBCODE&state='.$state);
        $response->assertRedirectContains('/auth/facebook/select?pending=');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);

        return $q['pending'];
    }

    public function test_login_redirects_to_facebook_with_configured_scopes(): void
    {
        $response = $this->actingAs($this->admin)->get('/auth/facebook');

        $response->assertRedirectContains('https://www.facebook.com/v25.0/dialog/oauth?');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('1111', $q['client_id']);
        $this->assertSame(route('auth.facebook.callback'), $q['redirect_uri']);
        $this->assertStringContainsString('pages_manage_metadata', $q['scope']);
        $this->assertStringContainsString('instagram_manage_comments', $q['scope']);
    }

    public function test_connect_routes_now_require_login(): void
    {
        $this->get('/auth/facebook')->assertRedirect('/login');
        $this->get('/auth/facebook/callback?code=x')->assertRedirect('/login');
    }

    public function test_callback_exchanges_for_a_long_lived_token_and_follows_pagination(): void
    {
        $this->fakeFacebook();
        $this->loginAndGetPending();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/access_token') && ($r['fb_exchange_token'] ?? null) === 'SHORT-USER-TOKEN'
            && $r['grant_type'] === 'fb_exchange_token' && $r['client_secret'] === 'fb-secret');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/me/accounts') && ($r['access_token'] ?? null) === 'LONG-USER-TOKEN');
        Http::assertSentCount(4);   // short token, long token, accounts page 1, accounts page 2
        $this->assertSame(0, SocialAccount::count(), 'nothing is connected before the user chooses');
    }

    public function test_picker_lists_pages_without_leaking_tokens(): void
    {
        $this->fakeFacebook();
        $pending = $this->loginAndGetPending();

        $response = $this->get("/auth/facebook/select?pending={$pending}")->assertOk();

        $response->assertInertia(fn ($p) => $p->component('Settings/SelectMetaPages')
            ->has('pages', 2)                                    // the page without a token is skipped
            ->where('pages.0.name', 'Acme Shop')
            ->where('pages.0.instagram.username', 'acme_shop')
            ->where('pages.0.state', 'available')
            ->where('pages.1.instagram', null));

        foreach (['PAGE1-TOKEN', 'PAGE2-TOKEN', 'LONG-USER-TOKEN'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_only_the_selected_items_are_connected_and_instagram_is_linked_to_its_page(): void
    {
        $this->fakeFacebook();
        $pending = $this->loginAndGetPending();

        $this->post('/auth/facebook/select', ['pending' => $pending, 'selections' => [
            ['page_id' => 'PAGE1', 'page' => true, 'instagram' => true],
            ['page_id' => 'PAGE2', 'page' => false, 'instagram' => false],
        ]])->assertRedirect('/settings/social-accounts')->assertSessionHas('success', fn ($m) => str_contains($m, '2 accounts'));

        $page = SocialAccount::where('platform', 'facebook')->firstOrFail();
        $ig = SocialAccount::where('platform', 'instagram')->firstOrFail();

        $this->assertSame('PAGE1', $page->platform_account_id);
        $this->assertSame('PAGE1-TOKEN', $page->access_token);          // the Page token, not the user token
        $this->assertNull($page->token_expires_at);                      // Page tokens from a long-lived user token don't expire
        $this->assertSame('facebook_login', $page->auth_mode);
        $this->assertSame('IG1', $ig->platform_account_id);
        $this->assertSame('@acme_shop', $ig->platform_account_name);     // a real name, not "Instagram (123)"
        $this->assertSame($page->id, $ig->parent_account_id);
        $this->assertSame('PAGE1-TOKEN', $ig->access_token);
        $this->assertFalse($ig->isInstagramLogin());
        $this->assertSame($this->org->id, $ig->organization_id);
        $this->assertNull(SocialAccount::where('platform_account_id', 'PAGE2')->first());
        $this->assertNotNull($page->webhook_subscribed_at);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v25.0/PAGE1/subscribed_apps'
            && $r['subscribed_fields'] === 'feed' && $r['access_token'] === 'PAGE1-TOKEN');
        Queue::assertPushed(SyncFacebookComments::class);
        Queue::assertPushed(SyncInstagramComments::class);
    }

    public function test_instagram_can_be_connected_without_connecting_its_facebook_page(): void
    {
        $this->fakeFacebook();
        $pending = $this->loginAndGetPending();

        $this->post('/auth/facebook/select', ['pending' => $pending, 'selections' => [
            ['page_id' => 'PAGE1', 'page' => false, 'instagram' => true],
        ]])->assertSessionHas('success');

        $this->assertNull(SocialAccount::where('platform', 'facebook')->first());
        $this->assertNull(SocialAccount::where('platform', 'instagram')->firstOrFail()->parent_account_id);
    }

    public function test_several_facebook_logins_accumulate_accounts_in_one_organization(): void
    {
        $this->fakeFacebook();
        $this->post('/auth/facebook/select', ['pending' => $this->loginAndGetPending(), 'selections' => [['page_id' => 'PAGE1', 'page' => true]]]);

        // a second Facebook user (different login) brings other Pages
        $this->fakeFacebook([['id' => 'PAGE9', 'name' => 'Other Brand', 'access_token' => 'PAGE9-TOKEN']]);
        $this->post('/auth/facebook/select', ['pending' => $this->loginAndGetPending(), 'selections' => [['page_id' => 'PAGE9', 'page' => true]]]);

        $this->assertSame(['PAGE1', 'PAGE9'], SocialAccount::orderBy('id')->pluck('platform_account_id')->all());
    }

    public function test_tampered_page_ids_are_ignored(): void
    {
        $this->fakeFacebook();
        $pending = $this->loginAndGetPending();

        $this->post('/auth/facebook/select', ['pending' => $pending, 'selections' => [
            ['page_id' => 'SOMEONE-ELSES-PAGE', 'page' => true],
        ]])->assertSessionHas('error');

        $this->assertSame(0, SocialAccount::count());
    }

    public function test_pending_selection_is_private_to_the_user_that_started_it(): void
    {
        $this->fakeFacebook();
        $pending = $this->loginAndGetPending();

        $colleague = $this->userIn($this->org, 'admin');
        $this->actingAs($colleague)->get("/auth/facebook/select?pending={$pending}")->assertRedirect('/settings/social-accounts')->assertSessionHas('error');

        $outsider = $this->userIn($this->org(), 'admin');
        $this->actingAs($outsider)->post('/auth/facebook/select', ['pending' => $pending, 'selections' => [['page_id' => 'PAGE1', 'page' => true]]])
            ->assertSessionHas('error');
        $this->assertSame(0, SocialAccount::withoutGlobalScopes()->count());
    }

    public function test_pages_owned_by_another_organization_are_refused_and_marked_in_the_picker(): void
    {
        $this->fakeFacebook();
        $other = $this->org();
        $this->account($other, 'facebook', ['platform_account_id' => 'PAGE1']);
        $pending = $this->loginAndGetPending();

        $this->get("/auth/facebook/select?pending={$pending}")
            ->assertInertia(fn ($p) => $p->where('pages.0.state', 'other_organization'));

        $this->post('/auth/facebook/select', ['pending' => $pending, 'selections' => [['page_id' => 'PAGE1', 'page' => true], ['page_id' => 'PAGE2', 'page' => true]]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'another organization'))
            ->assertSessionHas('success');

        $this->assertSame($other->id, SocialAccount::withoutGlobalScopes()->where('platform_account_id', 'PAGE1')->value('organization_id'));
        $this->assertSame($this->org->id, SocialAccount::withoutGlobalScopes()->where('platform_account_id', 'PAGE2')->value('organization_id'));
    }

    public function test_plan_limit_stops_the_selection_part_way(): void
    {
        $this->fakeFacebook();
        $this->org->update(['max_social_accounts' => 2]);
        $pending = $this->loginAndGetPending();

        $this->post('/auth/facebook/select', ['pending' => $pending, 'selections' => [
            ['page_id' => 'PAGE1', 'page' => true, 'instagram' => true],
            ['page_id' => 'PAGE2', 'page' => true],
        ]])->assertSessionHas('error', fn ($m) => str_contains($m, 'allows 2'));

        $this->assertSame(2, SocialAccount::count());
        $this->assertNull(SocialAccount::where('platform_account_id', 'PAGE2')->first());
    }

    public function test_pending_selection_can_only_be_used_once(): void
    {
        $this->fakeFacebook();
        $pending = $this->loginAndGetPending();
        $payload = ['pending' => $pending, 'selections' => [['page_id' => 'PAGE1', 'page' => true]]];

        $this->post('/auth/facebook/select', $payload)->assertSessionHas('success');
        $this->post('/auth/facebook/select', $payload)->assertSessionHas('error');
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_user_who_denies_access_gets_a_clear_message(): void
    {
        $state = OAuthState::issue('facebook', $this->admin->id, $this->org->id, $this->metaCred->id);

        $this->actingAs($this->admin)->get("/auth/facebook/callback?error=access_denied&error_description=Denied&state={$state}")
            ->assertRedirect('/settings/social-accounts')->assertSessionHas('error', fn ($m) => str_contains($m, 'cancelled'));
    }

    public function test_login_without_any_pages_explains_what_to_do(): void
    {
        Http::fake([
            'graph.facebook.com/v25.0/oauth/access_token*' => Http::sequence()->push(['access_token' => 's'])->push(['access_token' => 'l']),
            'graph.facebook.com/v25.0/me/accounts*' => Http::response(['data' => []]),
        ]);
        $state = OAuthState::issue('facebook', $this->admin->id, $this->org->id, $this->metaCred->id);

        $this->actingAs($this->admin)->get("/auth/facebook/callback?code=c&state={$state}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No Facebook Pages'));
    }
}
