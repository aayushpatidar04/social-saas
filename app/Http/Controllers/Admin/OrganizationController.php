<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    private const PLANS = ['free', 'starter', 'professional', 'enterprise'];

    public function index(Request $request)
    {
        $organizations = Organization::query()
            ->withCount(['users', 'socialAccounts'])
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%")))
            ->when($request->query('status') === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when($request->query('status') === 'active', fn ($q) => $q->whereNull('suspended_at')->where('is_active', true))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Organization $o) => $o->toArray() + [
                'usable' => $o->isUsable(),
                'max_users_effective' => $o->limit('users'),
                'max_social_accounts_effective' => $o->limit('social_accounts'),
            ]);

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $organizations,
            'filters' => $request->only('search', 'status'),
            'plans' => self::PLANS,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Organizations/Create', ['plans' => self::PLANS, 'plan_defaults' => config('plans')]);
    }

    public function store(Request $request, InvitationService $invitations)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:100', 'unique:organizations,slug'],
            'plan' => ['required', Rule::in(self::PLANS)],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_social_accounts' => ['nullable', 'integer', 'min:1'],
            'allow_custom_apps' => ['boolean'],
            'plan_expires_at' => ['nullable', 'date', 'after:today'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        [$organization, $url] = DB::transaction(function () use ($data, $request, $invitations) {
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'plan' => $data['plan'],
                'max_users' => $data['max_users'] ?? null,
                'max_social_accounts' => $data['max_social_accounts'] ?? null,
                'allow_custom_apps' => $data['allow_custom_apps'] ?? false,
                'plan_expires_at' => $data['plan_expires_at'] ?? null,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            [, $url] = $invitations->invite($organization, $data['admin_email'], 'admin', $request->user());

            return [$organization, $url];
        });

        return redirect()->route('admin.organizations.show', $organization)
            ->with('success', "Organization created. An invitation was emailed to {$data['admin_email']}.")
            ->with('invite_url', $url);
    }

    public function show(Organization $organization)
    {
        $organization->loadCount(['users', 'socialAccounts']);

        return Inertia::render('Admin/Organizations/Show', [
            'organization' => $organization->toArray() + [
                'usable' => $organization->isUsable(),
                'max_users_effective' => $organization->limit('users'),
                'max_social_accounts_effective' => $organization->limit('social_accounts'),
            ],
            'users' => $organization->users()->orderBy('name')->get(['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at']),
            'accounts' => $organization->socialAccounts()->latest()->get(['id', 'platform', 'platform_account_name', 'auth_mode', 'status', 'token_expires_at', 'last_synced_at']),
            'invitations' => $organization->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->get(['id', 'email', 'role', 'expires_at']),
            'own_apps' => $organization->credentials()->get()->map->toPresentation(),
            'plans' => self::PLANS,
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', Rule::in(self::PLANS)],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_social_accounts' => ['nullable', 'integer', 'min:1'],
            'allow_custom_apps' => ['boolean'],
            'plan_expires_at' => ['nullable', 'date'],
        ]);

        $organization->update($data + ['allow_custom_apps' => $data['allow_custom_apps'] ?? false]);

        return back()->with('success', 'Organization updated.');
    }

    public function suspend(Request $request, Organization $organization)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $organization->update(['suspended_at' => now(), 'suspended_reason' => $data['reason'] ?? null]);
        Log::info('Organization suspended', ['organization' => $organization->id, 'by' => $request->user()->id]);

        return back()->with('success', 'Organization suspended. Its users are blocked and syncing is paused.');
    }

    public function activate(Request $request, Organization $organization)
    {
        $organization->update(['suspended_at' => null, 'suspended_reason' => null, 'is_active' => true]);
        Log::info('Organization reactivated', ['organization' => $organization->id, 'by' => $request->user()->id]);

        return back()->with('success', 'Organization reactivated.');
    }

    public function destroy(Request $request, Organization $organization)
    {
        $request->validate(['confirm' => ['required', 'in:'.$organization->slug]]);

        Log::warning('Organization archived', ['organization' => $organization->id, 'slug' => $organization->slug, 'by' => $request->user()->id]);
        $organization->delete();

        return redirect()->route('admin.organizations.index')->with('success', 'Organization archived. Its users are blocked, syncing and webhooks are stopped; the data is retained.');
    }

    public function invite(Request $request, Organization $organization, InvitationService $invitations)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'manager', 'team_member', 'viewer'])],
        ]);

        [, $url] = $invitations->invite($organization, $data['email'], $data['role'], $request->user());

        return back()->with('success', "Invitation created for {$data['email']}.")->with('invite_url', $url);
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'organization';
        $slug = $base;
        for ($i = 2; Organization::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
