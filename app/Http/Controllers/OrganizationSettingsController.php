<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Services\OllamaService;

class OrganizationSettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $organization = $user->organization;

        return Inertia::render('Settings/Organization', [
            'organization' => $organization->only(['id', 'name', 'description', 'website', 'logo_url', 'plan', 'plan_expires_at']),
            'aiPrompts' => array_replace(OllamaService::defaultPrompts(), $organization->ai_prompts ?? []),
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
            'ai_prompts' => ['sometimes', 'array:analysis,response,intent,sentiment,lead_detection'],
            'ai_prompts.analysis' => ['required_with:ai_prompts', 'string', 'max:10000'],
            'ai_prompts.response' => ['required_with:ai_prompts', 'string', 'max:10000'],
            'ai_prompts.intent' => ['required_with:ai_prompts', 'string', 'max:10000'],
            'ai_prompts.sentiment' => ['required_with:ai_prompts', 'string', 'max:10000'],
            'ai_prompts.lead_detection' => ['required_with:ai_prompts', 'string', 'max:10000'],
        ]);

        Auth::user()->organization->update($data);

        return back()->with('success', 'Organization updated.');
    }
}
