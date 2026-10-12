<?php

namespace App\Services;

use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationService
{
    /** @return array{0: OrganizationInvitation, 1: string} the invitation and the one-time accept URL */
    public function invite(Organization $org, string $email, string $role, ?User $by): array
    {
        $email = strtolower(trim($email));

        // Replace any earlier pending invitation to the same address.
        $org->invitations()->where('email', $email)->whereNull('accepted_at')->delete();

        $token = Str::random(48);
        $invitation = $org->invitations()->create([
            'invited_by' => $by?->id,
            'email' => $email,
            'role' => $role,
            'token_hash' => OrganizationInvitation::hash($token),
            'expires_at' => now()->addDays(7),
        ]);

        $url = route('invitations.show', ['token' => $token]);

        try {
            Mail::to($email)->send(new OrganizationInvitationMail($org, $role, $url));
        } catch (\Throwable $e) {
            // Mail may not be configured; the inviter still gets the link to share manually.
            Log::warning('Invitation email could not be sent', ['email' => $email, 'error' => $e->getMessage()]);
        }

        return [$invitation, $url];
    }

    public function findPending(string $token): ?OrganizationInvitation
    {
        $invitation = OrganizationInvitation::where('token_hash', OrganizationInvitation::hash($token))->first();

        return $invitation?->isPending() ? $invitation : null;
    }
}
