<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeamController extends Controller
{
    private const ROLES = ['admin', 'manager', 'team_member', 'viewer'];

    public function index()
    {
        $user = Auth::user();
        $organization = $user->organization;

        return Inertia::render('Settings/Team', [
            'teamMembers' => $organization->users()->orderBy('name')->get(),
            'invitations' => $organization->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())
                ->get(['id', 'email', 'role', 'expires_at']),
            'currentUserId' => $user->id,
            'canManage' => $user->role === 'admin',
            'roles' => self::ROLES,
            'limits' => ['max' => $organization->limit('users'), 'used' => $organization->users()->count()],
        ]);
    }

    public function invite(Request $request, InvitationService $invitations)
    {
        $this->authorizeAdmin();
        $organization = Auth::user()->organization;

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        if (! $organization->canAddUser()) {
            return back()->with('error', "Your plan allows {$organization->limit('users')} users. Remove someone or ask your administrator to raise the limit.");
        }

        [, $url] = $invitations->invite($organization, $data['email'], $data['role'], Auth::user());

        return back()->with('success', "Invitation sent to {$data['email']}.")->with('invite_url', $url);
    }

    public function cancelInvitation(int $invitation)
    {
        $this->authorizeAdmin();

        Auth::user()->organization->invitations()->whereKey($invitation)->whereNull('accepted_at')->delete();

        return back()->with('success', 'Invitation cancelled.');
    }

    public function updateRole(Request $request, User $user)
    {
        $this->authorizeAdmin();
        $this->assertSameOrganization($user);

        $data = $request->validate(['role' => ['required', Rule::in(self::ROLES)]]);

        if ($user->role === 'admin' && $data['role'] !== 'admin' && $this->isLastAdmin($user)) {
            return back()->with('error', 'The organization needs at least one admin.');
        }

        $user->update(['role' => $data['role']]);

        return back()->with('success', 'Role updated.');
    }

    public function remove(User $user)
    {
        $this->authorizeAdmin();
        $this->assertSameOrganization($user);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot remove yourself.');
        }
        if ($user->role === 'admin' && $this->isLastAdmin($user)) {
            return back()->with('error', 'The organization needs at least one admin.');
        }

        $user->delete();

        return back()->with('success', 'Team member removed.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()->role === 'admin', 403, 'Only organization admins can manage the team.');
    }

    private function assertSameOrganization(User $user): void
    {
        abort_unless($user->organization_id === Auth::user()->organization_id && ! $user->is_super_admin, 404);
    }

    private function isLastAdmin(User $user): bool
    {
        return ! User::where('organization_id', $user->organization_id)
            ->where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->exists();
    }
}
