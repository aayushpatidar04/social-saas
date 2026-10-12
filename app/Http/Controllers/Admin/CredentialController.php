<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformCredential;
use App\Services\Credentials\CredentialManager;
use App\Services\Credentials\CredentialTester;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Platform-wide apps: the credentials every organization uses unless it has its own. */
class CredentialController extends Controller
{
    public function index()
    {
        $existing = PlatformCredential::where('scope_key', 'platform')->get()->keyBy('provider');

        return Inertia::render('Admin/Credentials/Index', [
            'providers' => collect(config('integrations.providers'))->map(fn ($schema, $key) => [
                'key' => $key,
                'label' => $schema['label'],
                'description' => $schema['description'] ?? null,
                'fields' => collect($schema['fields'])->map(fn ($f, $k) => $f + ['key' => $k])->values(),
                'has_webhook' => $schema['has_webhook'] ?? false,
                'byo_allowed' => $schema['byo_allowed'] ?? false,
                'callback_urls' => collect($schema['platforms'])->mapWithKeys(fn ($p) => [$p => $this->callback($p)])->all(),
                'credential' => $existing->get($key)?->toPresentation(),
            ])->values(),
        ]);
    }

    public function update(Request $request, string $provider, CredentialManager $manager)
    {
        $request->validate($manager->rules($provider));

        $manager->save($provider, null, $request->only('label', 'is_active', 'fields'), $request->user()->id);

        return back()->with('success', config("integrations.providers.{$provider}.label").' credentials saved.');
    }

    public function destroy(string $provider)
    {
        PlatformCredential::where('scope_key', 'platform')->where('provider', $provider)->delete();

        return back()->with('success', 'Credentials removed.');
    }

    public function test(string $provider, CredentialTester $tester)
    {
        $credential = PlatformCredential::where('scope_key', 'platform')->where('provider', $provider)->firstOrFail();

        $result = $tester->test($credential);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function callback(string $platform): string
    {
        return route(match ($platform) {
            'facebook' => 'auth.facebook.callback',
            'instagram' => 'auth.instagram.callback',
            default => $platform.'.callback',
        });
    }
}
