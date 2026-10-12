<?php

namespace Tests\Feature;

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

/** Connecting an Instagram professional account directly - no Facebook Page involved. */
class InstagramLoginFlowTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private const IG_ID = '17841400000000001';

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

    private function fakeInstagram(array $profile = [], bool $subscribe = true): void
    {
        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response(['data' => [['access_token' => 'SHORT-TOKEN', 'user_id' => self::IG_ID, 'permissions' => 'instagram_business_basic,instagram_business_manage_comments']]]),
            'graph.instagram.com/access_token*' => Http::response(['access_token' => 'LONG-TOKEN', 'token_type' => 'bearer', 'expires_in' => 5184000]),
            'graph.instagram.com/v25.0/me*' => Http::response($profile + ['user_id' => self::IG_ID, 'id' => '9999', 'username' => 'brand_shop', 'name' => 'Brand Shop', 'account_type' => 'BUSINESS']),
            'graph.instagram.com/v25.0/'.self::IG_ID.'/subscribed_apps' => Http::response(['success' => $subscribe], $subscribe ? 200 : 400),
        ]);
    }

    private function completeLogin(?User $as = null, ?string $state = null)
    {
        $as ??= $this->admin;
        $state ??= OAuthState::issue('instagram', $as->id, $as->organization_id, $this->metaCred->id);

        return $this->actingAs($as)->get('/auth/instagram/callback?code=AUTHCODE%23_&state='.$state);
    }

    public function test_login_redirects_to_instagram_with_the_instagram_app_id_not_the_facebook_one(): void
    {
        $response = $this->actingAs($this->admin)->get('/auth/instagram');

        $response->assertRedirectContains('https://www.instagram.com/oauth/authorize?');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);

        $this->assertSame('2222', $q['client_id']);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame(route('auth.instagram.callback'), $q['redirect_uri']);
        $this->assertSame('instagram_business_basic,instagram_business_manage_comments', $q['scope']);
        $this->assertSame('true', $q['force_reauth']);
        $this->assertNotEmpty($q['state']);
    }

    public function test_callback_connects_the_account_with_a_long_lived_token_and_subscribes_webhooks(): void
    {
        $this->fakeInstagram();

        $this->completeLogin()->assertRedirect('/settings/social-accounts')->assertSessionHas('success');

        $account = SocialAccount::firstOrFail();
        $this->assertSame('instagram', $account->platform);
        $this->assertSame(self::IG_ID, $account->platform_account_id);   // the id webhooks use
        $this->assertSame('@brand_shop', $account->platform_account_name);
        $this->assertSame('instagram_login', $account->auth_mode);
        $this->assertTrue($account->isInstagramLogin());
        $this->assertNull($account->parent_account_id);                   // no Facebook Page
        $this->assertSame($this->org->id, $account->organization_id);
        $this->assertSame($this->metaCred->id, $account->credential_id);
        $this->assertSame('LONG-TOKEN', $account->access_token);
        $this->assertEqualsWithDelta(60, now()->diffInDays($account->token_expires_at, false), 1);
        $this->assertNotNull($account->webhook_subscribed_at);
        $this->assertSame('9999', $account->metadata['app_scoped_id']);
        $this->assertStringNotContainsString('LONG-TOKEN', \DB::table('social_accounts')->value('access_token'));

        Queue::assertPushed(SyncInstagramComments::class);
    }

    public function test_the_exact_requests_meta_expects_are_made(): void
    {
        $this->fakeInstagram();
        $this->completeLogin();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.instagram.com/oauth/access_token'
            && $r['client_id'] === '2222' && $r['client_secret'] === 'ig-secret'
            && $r['grant_type'] === 'authorization_code' && $r['code'] === 'AUTHCODE'   // trailing "#_" stripped
            && $r['redirect_uri'] === route('auth.instagram.callback'));

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://graph.instagram.com/access_token')
            && $r['grant_type'] === 'ig_exchange_token' && $r['access_token'] === 'SHORT-TOKEN' && $r['client_secret'] === 'ig-secret');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.instagram.com/v25.0/'.self::IG_ID.'/subscribed_apps'
            && $r->method() === 'POST' && $r['subscribed_fields'] === 'comments' && $r['access_token'] === 'LONG-TOKEN');
    }

    public function test_reconnecting_updates_the_same_row(): void
    {
        $this->fakeInstagram();
        $this->completeLogin();
        $this->completeLogin();

        $this->assertSame(1, SocialAccount::count());
    }

    public function test_webhook_subscription_failure_still_connects_but_warns(): void
    {
        $this->fakeInstagram(subscribe: false);

        $this->completeLogin()->assertSessionHas('success', fn ($m) => str_contains($m, 'webhook'));
        $this->assertNull(SocialAccount::firstOrFail()->webhook_subscribed_at);
    }

    public function test_personal_accounts_are_rejected_with_a_helpful_message(): void
    {
        $this->fakeInstagram(['account_type' => 'PERSONAL']);

        $this->completeLogin()->assertSessionHas('error', fn ($m) => str_contains($m, 'Business or Creator'));
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_invalid_missing_or_foreign_state_is_rejected(): void
    {
        $this->fakeInstagram();

        $this->actingAs($this->admin)->get('/auth/instagram/callback?code=x&state=forged')->assertSessionHas('error');

        // a state issued to a different user cannot be completed by this user
        $other = $this->userIn($this->org(), 'admin');
        $stateOfOther = OAuthState::issue('instagram', $other->id, $other->organization_id, $this->metaCred->id);
        $this->completeLogin($this->admin, $stateOfOther)->assertSessionHas('error');

        // states are single use
        $state = OAuthState::issue('instagram', $this->admin->id, $this->org->id, $this->metaCred->id);
        $this->completeLogin($this->admin, $state)->assertSessionHas('success');
        $this->completeLogin($this->admin, $state)->assertSessionHas('error');

        $this->assertSame(1, SocialAccount::count());
    }

    public function test_an_account_owned_by_another_organization_cannot_be_taken(): void
    {
        $this->fakeInstagram();
        $victim = $this->org();
        $existing = $this->account($victim, 'instagram', ['platform_account_id' => self::IG_ID]);

        $this->completeLogin()->assertSessionHas('error', fn ($m) => str_contains($m, 'already connected to another organization'));

        $this->assertSame($victim->id, $existing->fresh()->organization_id);
        $this->assertSame(1, SocialAccount::withoutGlobalScopes()->count());
    }

    public function test_plan_limit_is_enforced(): void
    {
        $this->fakeInstagram();
        $this->org->update(['max_social_accounts' => 1]);
        $this->account($this->org, 'facebook');

        $this->completeLogin()->assertSessionHas('error', fn ($m) => str_contains($m, 'allows 1 connected'));
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_not_configured_and_non_managers_get_clear_errors(): void
    {
        PlatformCredential::query()->delete();
        $this->actingAs($this->admin)->get('/auth/instagram')->assertRedirect('/settings/social-accounts')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'not configured'));

        $this->metaApp();
        $viewer = $this->userIn($this->org, 'viewer');
        $this->actingAs($viewer)->get('/auth/instagram')->assertSessionHas('error', fn ($m) => str_contains($m, 'admins and managers'));
    }

    public function test_organization_with_its_own_meta_app_uses_it(): void
    {
        $this->org->update(['allow_custom_apps' => true]);
        $own = $this->metaApp($this->org, ['instagram_app_id' => 'OWN-IG-ID', 'instagram_app_secret' => 'own-secret']);

        $response = $this->actingAs($this->admin)->get('/auth/instagram');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('OWN-IG-ID', $q['client_id']);

        $this->fakeInstagram();
        $state = OAuthState::issue('instagram', $this->admin->id, $this->org->id, $own->id);
        $this->completeLogin($this->admin, $state);

        $this->assertSame($own->id, SocialAccount::firstOrFail()->credential_id);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.instagram.com/oauth/access_token' && $r['client_secret'] === 'own-secret');
    }
}
