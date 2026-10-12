<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PlatformCredential;
use App\Models\SocialAccount;
use App\Models\SocialComment;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $configured = PlatformCredential::where('scope_key', 'platform')->where('is_active', true)->get()
            ->filter->isConfigured()->pluck('provider')->all();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'organizations' => Organization::count(),
                'active_organizations' => Organization::where('is_active', true)->whereNull('suspended_at')->count(),
                'suspended_organizations' => Organization::whereNotNull('suspended_at')->count(),
                'users' => User::where('is_super_admin', false)->count(),
                'social_accounts' => SocialAccount::count(),
                'accounts_needing_attention' => SocialAccount::whereIn('status', ['expired', 'error'])->count(),
                'comments_7d' => SocialComment::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'accounts_by_platform' => SocialAccount::selectRaw('platform, count(*) as total')->groupBy('platform')->pluck('total', 'platform'),
            'recent_organizations' => Organization::withCount(['users', 'socialAccounts'])->latest()->limit(6)->get(),
            'providers' => collect(config('integrations.providers'))->map(fn ($p, $key) => [
                'key' => $key,
                'label' => $p['label'],
                'configured' => in_array($key, $configured, true),
            ])->values(),
        ]);
    }
}
