<?php

namespace Tests\Feature;

use App\Jobs\ProcessMetaWebhook;
use App\Models\PlatformCredential;
use App\Models\SocialComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class MetaWebhookTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private PlatformCredential $platformApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->platformApp = $this->metaApp();
    }

    private function signedPost(string $url, array $payload, ?string $secret, bool $tamper = false)
    {
        $body = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($secret !== null) {
            $server['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $tamper ? $body.' ' : $body, $secret);
        }

        return $this->call('POST', $url, [], [], [], $server, $body);
    }

    private function payload(string $object = 'instagram', string $entryId = 'IG1'): array
    {
        return ['object' => $object, 'entry' => [['id' => $entryId, 'time' => time(), 'changes' => [['field' => 'comments', 'value' => ['id' => 'C1', 'verb' => 'add']]]]]];
    }

    // ---------------------------------------------------------------- verification (GET)

    public function test_meta_verification_handshake(): void
    {
        $url = "/webhooks/meta/{$this->platformApp->webhook_key}";

        $this->get("{$url}?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=12345")->assertOk()->assertSee('12345');
        $this->get("{$url}?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345")->assertForbidden();
        $this->get("{$url}?hub_mode=subscribe&hub_challenge=12345")->assertForbidden();
        $this->get('/webhooks/meta/unknown-key?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=1')->assertForbidden();
    }

    public function test_legacy_unkeyed_url_is_served_by_the_platform_app(): void
    {
        $this->get('/webhooks/meta?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=777')->assertOk()->assertSee('777');
    }

    public function test_verify_token_comes_from_the_database_not_env(): void
    {
        config(['services.facebook.verify_token' => 'from-env']);
        $this->get('/webhooks/meta?hub_mode=subscribe&hub_verify_token=from-env&hub_challenge=1')->assertForbidden();
    }

    // ---------------------------------------------------------------- signatures (POST)

    public function test_unsigned_and_wrongly_signed_events_are_rejected(): void
    {
        Queue::fake();
        $url = "/webhooks/meta/{$this->platformApp->webhook_key}";

        $this->signedPost($url, $this->payload(), null)->assertForbidden();
        $this->signedPost($url, $this->payload(), 'not-the-secret')->assertForbidden();
        $this->signedPost($url, $this->payload(), 'fb-secret', tamper: true)->assertForbidden();
        $this->signedPost('/webhooks/meta', $this->payload(), null)->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_events_signed_with_either_app_secret_are_accepted(): void
    {
        Queue::fake();
        $url = "/webhooks/meta/{$this->platformApp->webhook_key}";

        $this->signedPost($url, $this->payload('page', 'PAGE1'), 'fb-secret')->assertOk();   // Facebook product
        $this->signedPost($url, $this->payload(), 'ig-secret')->assertOk();                   // Instagram product

        Queue::assertPushed(ProcessMetaWebhook::class, 2);
        $this->assertNotNull($this->platformApp->fresh()->last_webhook_at);
    }

    public function test_each_meta_app_only_accepts_its_own_secret(): void
    {
        Queue::fake();
        $org = $this->org(['allow_custom_apps' => true]);
        $own = $this->metaApp($org, ['facebook_app_secret' => 'org-fb-secret', 'instagram_app_secret' => 'org-ig-secret']);

        $this->signedPost("/webhooks/meta/{$own->webhook_key}", $this->payload(), 'org-ig-secret')->assertOk();
        $this->signedPost("/webhooks/meta/{$own->webhook_key}", $this->payload(), 'fb-secret')->assertForbidden();   // platform secret on the org's URL
        $this->signedPost("/webhooks/meta/{$this->platformApp->webhook_key}", $this->payload(), 'org-fb-secret')->assertForbidden();

        Queue::assertPushed(ProcessMetaWebhook::class, 1);
    }

    public function test_inactive_apps_do_not_receive_events(): void
    {
        Queue::fake();
        $this->platformApp->update(['is_active' => false]);

        $this->signedPost("/webhooks/meta/{$this->platformApp->webhook_key}", $this->payload(), 'fb-secret')->assertForbidden();
    }

    public function test_youtube_and_linkedin_webhooks_are_no_longer_blocked_by_csrf(): void
    {
        // they used to answer 419 because only webhooks/meta was exempt
        $this->assertNotSame(419, $this->postJson('/webhooks/youtube', [])->status());
        $this->assertNotSame(419, $this->postJson('/webhooks/linkedin', [])->status());
    }

    // ---------------------------------------------------------------- processing

    private function commentLookup(string $igId = 'IG1'): void
    {
        Http::fake([
            'graph.instagram.com/v25.0/C1*' => Http::response([
                'id' => 'C1', 'text' => 'How much is this?', 'timestamp' => now()->toIso8601String(),
                'from' => ['id' => 'customer-9', 'username' => 'curious_customer'],
                'media' => ['id' => 'M1', 'caption' => 'New drop', 'permalink' => 'https://instagram.com/p/abc', 'media_type' => 'IMAGE'],
            ]),
        ]);
    }

    public function test_instagram_event_is_routed_to_the_account_by_id_with_a_single_lookup(): void
    {
        Queue::fake();
        $acme = $this->org();
        $globex = $this->org();
        $target = $this->account($acme, 'instagram', ['platform_account_id' => 'IG1', 'auth_mode' => 'instagram_login']);
        foreach (range(1, 5) as $i) {                        // unrelated accounts used to each cost an HTTP call
            $this->account($globex, 'facebook');
            $this->account($globex, 'instagram');
        }
        $this->commentLookup();

        (new ProcessMetaWebhook($this->payload()))->handle();

        $comment = SocialComment::withoutGlobalScopes()->where('platform_comment_id', 'C1')->firstOrFail();
        $this->assertSame($target->id, $comment->social_account_id);
        $this->assertSame($acme->id, $comment->organization_id);
        $this->assertSame('instagram', $comment->platform);
        // the only request is the comment fetch, made against graph.instagram.com for this account
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://graph.instagram.com/v25.0/C1') && $r['access_token'] === $target->access_token);
    }

    public function test_instagram_accounts_connected_through_facebook_use_graph_facebook_com(): void
    {
        Queue::fake();
        $acct = $this->account($this->org(), 'instagram', ['platform_account_id' => 'IG1', 'auth_mode' => 'facebook_login']);
        Http::fake(['graph.facebook.com/v25.0/C1*' => Http::response([
            'id' => 'C1', 'text' => 'Hi', 'timestamp' => now()->toIso8601String(), 'from' => ['id' => 'u', 'username' => 'u'],
            'media' => ['id' => 'M1', 'caption' => 'c', 'permalink' => 'https://instagram.com/p/x', 'media_type' => 'IMAGE']])]);

        (new ProcessMetaWebhook($this->payload()))->handle();

        $this->assertSame(1, SocialComment::withoutGlobalScopes()->where('social_account_id', $acct->id)->count());
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://graph.facebook.com/v25.0/C1'));
    }

    public function test_events_for_unknown_accounts_or_suspended_organizations_are_ignored_without_any_api_call(): void
    {
        Queue::fake();
        Http::fake();
        $suspended = $this->org()->forceFill(['suspended_at' => now()]);
        $suspended->save();
        $this->account($suspended, 'instagram', ['platform_account_id' => 'IG1']);

        (new ProcessMetaWebhook($this->payload()))->handle();                         // suspended
        (new ProcessMetaWebhook($this->payload('instagram', 'NOBODY')))->handle();    // unknown

        Http::assertNothingSent();
        $this->assertSame(0, SocialComment::withoutGlobalScopes()->count());
    }
}
