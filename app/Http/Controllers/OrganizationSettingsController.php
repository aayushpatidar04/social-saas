<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrganizationSettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $organization = $user->organization;

        return Inertia::render('Settings/Organization', [
            'organization' => $organization->only(['id', 'name', 'description', 'website', 'logo_url', 'plan', 'plan_expires_at']),
            'usage' => [
                'users' => ['used' => $organization->users()->count(), 'max' => $organization->limit('users')],
                'social_accounts' => [
                    'used' => $organization->socialAccounts()->where('status', '!=', 'disconnected')->count(),
                    'max' => $organization->limit('social_accounts'),
                ],
            ],
            'canEdit' => $user->role === 'admin',
        ]);
    }

    /** Organizations edit their profile only. Plan and limits are controlled by the platform. */
    public function update(Request $request)
    {
        abort_unless(Auth::user()->role === 'admin', 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        Auth::user()->organization->update($data);

        return back()->with('success', 'Organization updated.');
    }
}
