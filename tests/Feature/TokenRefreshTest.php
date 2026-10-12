<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class TokenRefreshTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private function igLogin(array $attrs = [])
    {
        return $this->account($this->org(), 'instagram', $attrs + [
            'auth_mode' => 'instagram_login', 'access_token' => 'OLD-TOKEN',
            'token_expires_at' => now()->addDays(5), 'token_refreshed_at' => now()->subDays(55),
        ]);
    }

    public function test_tokens_close_to_expiry_are_refreshed_for_another_60_days(): void
    {
        $account = $this->igLogin();
        Http::fake(['graph.instagram.com/refresh_access_token*' => Http::response(['access_token' => 'NEW-TOKEN', 'token_type' => 'bearer', 'expires_in' => 5184000])]);

        $this->artisan('meta:refresh-tokens')->assertSuccessful();

        $account->refresh();
        $this->assertSame('NEW-TOKEN', $account->access_token);
        $this->assertEqualsWithDelta(60, now()->diffInDays($account->token_expires_at, false), 1);
        Http::assertSent(fn (Request $r) => $r['grant_type'] === 'ig_refresh_token' && $r['access_token'] === 'OLD-TOKEN');
    }

    public function test_only_instagram_login_tokens_that_are_due_get_refreshed(): void
    {
        Http::fake(['graph.instagram.com/*' => Http::response(['access_token' => 'N', 'expires_in' => 5184000])]);
        $this->igLogin(['token_expires_at' => now()->addDays(40)]);                                   // plenty of time left
        $this->igLogin(['token_refreshed_at' => now()->subHours(3)]);                                  // refreshed <24h ago (Meta refuses)
        $this->account($this->org(), 'instagram', ['auth_mode' => 'facebook_login', 'token_expires_at' => now()->addDay()]); // page token
        $this->account($this->org(), 'facebook', ['token_expires_at' => now()->addDay()]);

        $this->artisan('meta:refresh-tokens')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_token_that_can_no_longer_be_refreshed_marks_the_account_expired(): void
    {
        $account = $this->igLogin(['token_expires_at' => now()->subHour()]);
        Http::fake(['graph.instagram.com/*' => Http::response(['error' => ['message' => 'Session has expired', 'code' => 190]], 400)]);

        $this->artisan('meta:refresh-tokens')->assertSuccessful();

        $this->assertSame('expired', $account->fresh()->status);
        $this->assertStringContainsString('reconnect', strtolower($account->fresh()->error_message));
    }

    public function test_a_temporary_failure_before_expiry_keeps_the_account_connected(): void
    {
        $account = $this->igLogin();
        Http::fake(['graph.instagram.com/*' => Http::response(['error' => ['message' => 'Temporarily unavailable']], 500)]);

        $this->artisan('meta:refresh-tokens')->assertSuccessful();

        $this->assertSame('connected', $account->fresh()->status);
        $this->assertStringContainsString('Temporarily unavailable', $account->fresh()->error_message);
    }

    public function test_refresh_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode(' | ');
        $this->assertStringContainsString('meta:refresh-tokens', $events);
    }

    public function test_an_expired_account_asks_to_reauthorize_instead_of_silently_reconnecting(): void
    {
        $account = $this->igLogin(['status' => 'expired']);
        $admin = $this->userIn($account->organization, 'admin');

        $this->actingAs($admin)->postJson("/settings/social-accounts/{$account->id}/reconnect")
            ->assertStatus(409)->assertJsonPath('reauthorize_url', route('auth.instagram'));
    }
}
