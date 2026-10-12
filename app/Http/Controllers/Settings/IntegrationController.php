<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PlatformCredential;
use App\Services\Credentials\CredentialManager;
use App\Services\Credentials\CredentialTester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * "Bring your own app": an organization registers its own Meta / Google / X /
 * LinkedIn app so its accounts connect through its own brand and rate limits.
 * Only available when the super admin enabled it for the organization.
 */
class IntegrationController extends Controller
{
    public function index()
    {
        $organization = Auth::user()->organization;
        $enabled = $organization->allow_custom_apps;

        $own = $organization->credentials()->get()->keyBy('provider');
        $platform = PlatformCredential::where('scope_key', 'platform')->where('is_active', true)->get()
            ->filter->isConfigured()->pluck('provider')->all();

        return Inertia::render('Settings/Integrations', [
            'enabled' => $enabled,
            'canEdit' => Auth::user()->role === 'admin',
            'providers' => collect(config('integrations.providers'))
                ->filter(fn ($p) => $p['byo_allowed'] ?? false)
                ->map(fn ($schema, $key) => [
                    'key' => $key,
                    'label' => $schema['label'],
                    'description' => $schema['description'] ?? null,
                    'fields' => collect($schema['fields'])->map(fn ($f, $k) => $f + ['key' => $k])->values(),
                    'platform_default_available' => in_array($key, $platform, true),
                    'credential' => $own->get($key)?->toPresentation(),
                ])->values(),
        ]);
    }

    public function update(Request $request, string $provider, CredentialManager $manager)
    {
        $this->authorizeEdit($provider);
        $request->validate($manager->rules($provider));

        $manager->save($provider, Auth::user()->organization_id, $request->only('label', 'is_active', 'fields'), Auth::id());

        return back()->with('success', 'Credentials saved.');
    }

    public function destroy(string $provider)
    {
        $this->authorizeEdit($provider);

        Auth::user()->organization->credentials()->where('provider', $provider)->delete();

        return back()->with('success', 'Your own app was removed. The platform app will be used again.');
    }

    public function test(string $provider, CredentialTester $tester)
    {
        $this->authorizeEdit($provider);

        $credential = Auth::user()->organization->credentials()->where('provider', $provider)->firstOrFail();
        $result = $tester->test($credential);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function authorizeEdit(string $provider): void
    {
        abort_unless(Auth::user()->role === 'admin', 403);
        abort_unless(Auth::user()->organization->allow_custom_apps, 403, 'Custom apps are not enabled for your organization.');
        abort_unless(config("integrations.providers.{$provider}.byo_allowed"), 404);
    }
}
