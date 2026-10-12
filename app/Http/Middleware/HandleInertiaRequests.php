<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'organization' => fn () => ($org = $request->user()?->organization)
                ? ['id' => $org->id, 'name' => $org->name, 'plan' => $org->plan, 'allow_custom_apps' => (bool) $org->allow_custom_apps]
                : null,
            'impersonating' => fn () => $request->session()->has('impersonator_id'),
            // Messages set with ->with('success'|'error', ...) on redirects
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'invite_url' => $request->session()->get('invite_url'),
            ],
        ];
    }
}
