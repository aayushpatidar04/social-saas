<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Meta\OAuthState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class YoutubeChannelSelectionFlowTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    private Organization $org;

    private User $admin;

    private PlatformCredential $youtubeCred;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->org();
        $this->admin = $this->userIn($this->org, 'admin');
        $this->youtubeCred = PlatformCredential::create([
            'organization_id' => null,
            'provider' => 'youtube',
            'label' => 'Platform app',
            'data' => ['client_id' => 'google-client', 'client_secret' => 'google-secret'],
        ]);
    }

    private function fakeGoogle(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'youtube-access-secret',
                'refresh_token' => 'youtube-refresh-secret',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/youtube/v3/channels*' => Http::sequence()
                ->push([
                    'items' => [
                        ['id' => 'CHANNEL1', 'snippet' => ['title' => 'First Channel', 'customUrl' => '@first']],
                    ],
                    'nextPageToken' => 'NEXT',
                ])
                ->push([
                    'items' => [
                        ['id' => 'CHANNEL2', 'snippet' => ['title' => 'Second Channel']],
                    ],
                ]),
        ]);
    }

    private function callbackAndGetPending(): string
    {
        $this->fakeGoogle();
        $state = OAuthState::issue('youtube', $this->admin->id, $this->org->id, $this->youtubeCred->id);

        $response = $this->actingAs($this->admin)->get('/auth/youtube/callback?code=GOOGLECODE&state='.$state);
        $response->assertRedirectContains('/auth/youtube/select?pending=');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['pending'];
    }

    public function test_callback_lists_all_channels_and_does_not_connect_before_selection(): void
    {
        $pending = $this->callbackAndGetPending();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/youtube/v3/channels')
            && ($request['mine'] ?? null) === 'true');
        Http::assertSentCount(3);
        $this->assertSame(0, SocialAccount::count());

        $response = $this->get("/auth/youtube/select?pending={$pending}")->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Settings/SelectYoutubeChannels')
            ->has('channels', 2)
            ->where('channels.0.name', 'First Channel')
            ->where('channels.0.handle', '@first')
            ->where('channels.0.state', 'available')
            ->where('channels.1.name', 'Second Channel'));

        foreach (['youtube-access-secret', 'youtube-refresh-secret', 'google-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_only_selected_channel_is_connected_with_oauth_tokens(): void
    {
        $pending = $this->callbackAndGetPending();

        $this->actingAs($this->admin)->post('/auth/youtube/select', [
            'pending' => $pending,
            'channel_ids' => ['CHANNEL2'],
        ])->assertRedirect('/settings/social-accounts')
            ->assertSessionHas('success', 'Connected 1 YouTube channel.');

        $channel = SocialAccount::where('platform', 'youtube')->firstOrFail();
        $this->assertSame('CHANNEL2', $channel->platform_account_id);
        $this->assertSame('Second Channel', $channel->platform_account_name);
        $this->assertSame('youtube-access-secret', $channel->access_token);
        $this->assertSame('youtube-refresh-secret', $channel->refresh_token);
        $this->assertNull(SocialAccount::where('platform_account_id', 'CHANNEL1')->first());
    }

    public function test_selection_cannot_be_completed_by_another_user(): void
    {
        $pending = $this->callbackAndGetPending();
        $colleague = $this->userIn($this->org, 'member');

        $this->actingAs($colleague)
            ->get("/auth/youtube/select?pending={$pending}")
            ->assertRedirect('/settings/social-accounts')
            ->assertSessionHas('error');

        $this->actingAs($colleague)->post('/auth/youtube/select', [
            'pending' => $pending,
            'channel_ids' => ['CHANNEL1'],
        ])->assertRedirect('/settings/social-accounts')->assertSessionHas('error');

        $this->assertSame(0, SocialAccount::count());
    }
}
