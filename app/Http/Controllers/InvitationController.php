<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/** Public: accept an emailed invitation, set a password, join the organization. */
class InvitationController extends Controller
{
    public function show(string $token, InvitationService $invitations)
    {
        $invitation = $invitations->findPending($token);

        if (! $invitation) {
            return Inertia::render('Auth/InvitationInvalid');
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'email' => $invitation->email,
            'organization' => $invitation->organization->name,
            'role' => $invitation->role,
        ]);
    }

    public function accept(Request $request, string $token, InvitationService $invitations)
    {
        $invitation = $invitations->findPending($token);
        abort_unless($invitation, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (User::withTrashed()->where('email', $invitation->email)->exists()) {
            return back()->withErrors(['name' => 'An account with this email already exists.']);
        }

        $organization = $invitation->organization;
        if ($organization->users()->count() >= ($organization->limit('users') ?? PHP_INT_MAX)) {
            return back()->withErrors(['name' => 'This organization has reached its user limit. Ask the administrator for help.']);
        }

        $user = DB::transaction(function () use ($invitation, $data) {
            $user = new User(['name' => $data['name'], 'email' => $invitation->email, 'password' => Hash::make($data['password']), 'role' => $invitation->role, 'is_active' => true]);
            $user->organization_id = $invitation->organization_id;
            // Following the emailed link proves the address.
            $user->email_verified_at = now();
            $user->save();

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
