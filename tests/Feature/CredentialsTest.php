<?php

namespace Tests\Feature;

use App\Models\PlatformCredential;
use App\Services\Credentials\CredentialResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class CredentialsTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private function save(string $provider, array $fields, array $extra = [])
    {
        return $this->actingAs($this->superAdmin())->put("/admin/credentials/{$provider}", ['fields' => $fields] + $extra);
    }

    public function test_super_admin_stores_meta_credentials_encrypted_and_generates_a_verify_token(): void
    {
        $this->save('meta', ['facebook_app_id' => '1111', 'facebook_app_secret' => 'TOP-SECRET', 'instagram_app_id' => '2222', 'instagram_app_secret' => 'IG-TOP-SECRET'])
            ->assertRedirect();

        $cred = PlatformCredential::where('scope_key', 'platform')->where('provider', 'meta')->firstOrFail();
        $raw = \DB::table('platform_credentials')->first()->data;

        $this->assertStringNotContainsString('TOP-SECRET', $raw);
        $this->assertStringNotContainsString('1111', $raw);            // everything is encrypted, not just secrets
        $this->assertSame('TOP-SECRET', $cred->get('facebook_app_secret'));
        $this->assertSame(32, strlen($cred->get('webhook_verify_token')));   // generated
        $this->assertSame('v25.0', $cred->get('graph_version'));              // schema default
        $this->assertTrue($cred->supports('facebook'));
        $this->assertTrue($cred->supports('instagram'));
    }

    public function test_secrets_are_never_sent_back_to_the_browser(): void
    {
        $this->metaApp();

        $response = $this->actingAs($this->superAdmin())->get('/admin/credentials')->assertOk();

        foreach (['fb-secret', 'ig-secret', 'verify-me'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        $response->assertInertia(fn ($p) => $p->component('Admin/Credentials/Index')
            ->where('providers.0.credential.fields.facebook_app_secret.set', true)
            ->where('providers.0.credential.fields.facebook_app_id.value', '1111'));
    }

    public function test_blank_secret_keeps_the_stored_value_and_blank_plain_field_clears_it(): void
    {
        $this->metaApp();

        $this->save('meta', ['facebook_app_id' => '9999', 'facebook_app_secret' => '', 'instagram_app_id' => '2222', 'instagram_app_secret' => ''])->assertRedirect();

        $cred = PlatformCredential::first();
        $this->assertSame('9999', $cred->get('facebook_app_id'));
        $this->assertSame('fb-secret', $cred->get('facebook_app_secret'));
        $this->assertSame('ig-secret', $cred->get('instagram_app_secret'));
    }

    public function test_half_filled_or_empty_meta_pairs_are_rejected(): void
    {
        $this->save('meta', ['facebook_app_id' => '1111'])->assertSessionHasErrors('fields');
        $this->save('meta', [])->assertSessionHasErrors('fields');
        $this->assertSame(0, PlatformCredential::count());
    }

    public function test_instagram_only_setup_is_valid_and_does_not_enable_facebook(): void
    {
        $this->save('meta', ['instagram_app_id' => '2222', 'instagram_app_secret' => 'ig'])->assertRedirect();

        $r = app(CredentialResolver::class);
        $org = $this->org();
        $this->assertNotNull($r->forPlatform('instagram', $org->id));
        $this->assertNull($r->forPlatform('facebook', $org->id));
        $this->assertSame(['facebook' => false, 'instagram' => true, 'youtube' => false, 'twitter' => false, 'linkedin' => false], $r->availability($org->id));
    }

    public function test_tenant_users_cannot_manage_platform_credentials(): void
    {
        $user = $this->userIn($this->org());

        $this->actingAs($user)->get('/admin/credentials')->assertForbidden();
        $this->actingAs($user)->put('/admin/credentials/meta', ['fields' => ['facebook_app_id' => 'a', 'facebook_app_secret' => 'b']])->assertForbidden();
    }

    public function test_organization_uses_its_own_app_only_when_the_platform_allows_it(): void
    {
        $platform = $this->metaApp(null, ['facebook_app_id' => 'PLATFORM']);
        $org = $this->org(['allow_custom_apps' => false]);
        $own = $this->metaApp($org, ['facebook_app_id' => 'OWN']);
        $r = app(CredentialResolver::class);

        $this->assertSame($platform->id, $r->forPlatform('facebook', $org->id)->id, 'not allowed -> platform app');

        $org->update(['allow_custom_apps' => true]);
        $this->assertSame($own->id, $r->forPlatform('facebook', $org->id)->id, 'allowed -> own app');

        $own->update(['is_active' => false]);
        $this->assertSame($platform->id, $r->forPlatform('facebook', $org->id)->id, 'inactive own app -> platform app');

        $other = $this->org(['allow_custom_apps' => true]);
        $this->assertSame($platform->id, $r->forPlatform('facebook', $other->id)->id, 'another org never sees $org\'s app');
    }

    public function test_org_admin_manages_own_app_through_integrations_page(): void
    {
        $org = $this->org(['allow_custom_apps' => true]);
        $admin = $this->userIn($org, 'admin');

        $this->actingAs($admin)->put('/settings/integrations/meta', ['fields' => ['facebook_app_id' => 'ORG', 'facebook_app_secret' => 'ORG-SECRET']])->assertRedirect();

        $cred = $org->credentials()->firstOrFail();
        $this->assertSame((string) $org->id, $cred->scope_key);
        $this->assertSame('ORG-SECRET', $cred->get('facebook_app_secret'));

        $page = $this->actingAs($admin)->get('/settings/integrations')->assertOk();
        $this->assertStringNotContainsString('ORG-SECRET', $page->getContent());
    }

    public function test_integrations_are_forbidden_when_not_enabled_or_not_admin(): void
    {
        $locked = $this->org(['allow_custom_apps' => false]);
        $this->actingAs($this->userIn($locked, 'admin'))
            ->put('/settings/integrations/meta', ['fields' => ['facebook_app_id' => 'a', 'facebook_app_secret' => 'b']])->assertForbidden();

        $open = $this->org(['allow_custom_apps' => true]);
        $this->actingAs($this->userIn($open, 'manager'))
            ->put('/settings/integrations/meta', ['fields' => ['facebook_app_id' => 'a', 'facebook_app_secret' => 'b']])->assertForbidden();

        // infrastructure providers can never be tenant-owned
        $this->actingAs($this->userIn($open, 'admin'))
            ->put('/settings/integrations/ollama', ['fields' => ['base_url' => 'http://x']])->assertNotFound();
    }

    public function test_credential_test_button_verifies_meta_credentials_against_graph(): void
    {
        $this->metaApp();
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['name' => 'My Meta App', 'id' => '1111'])
            ->push(['error' => ['message' => 'Invalid client_secret']], 400)]);

        $this->actingAs($this->superAdmin())->post('/admin/credentials/meta/test')
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'My Meta App'));
        $this->assertNotNull(PlatformCredential::first()->last_verified_at);

        $this->actingAs($this->superAdmin())->post('/admin/credentials/meta/test')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Invalid client_secret'));
    }

    public function test_env_import_command_copies_existing_settings_into_the_database(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "FACEBOOK_APP_ID=555\nFACEBOOK_APP_SECRET=fbsecret\nMETA_WEBHOOK_VERIFY_TOKEN=tok\nYOUTUBE_CLIENT_ID=yt\nYOUTUBE_CLIENT_SECRET=ytsecret\n");

        $this->artisan('saas:import-env', ['--file' => $file])->assertSuccessful();

        $meta = PlatformCredential::where('provider', 'meta')->firstOrFail();
        $this->assertSame('555', $meta->get('facebook_app_id'));
        $this->assertSame('tok', $meta->get('webhook_verify_token'));
        $this->assertSame('ytsecret', PlatformCredential::where('provider', 'youtube')->firstOrFail()->get('client_secret'));
        $this->assertNull(PlatformCredential::where('provider', 'twitter')->first());
        unlink($file);
    }
}
