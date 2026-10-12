<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/** Let a super admin see the product exactly as an organization's user does (support / debugging). */
class ImpersonationController extends Controller
{
    public function start(Request $request, Organization $organization, User $user)
    {
        abort_unless((int) $user->organization_id === (int) $organization->id && ! $user->is_super_admin, 404);

        $adminId = $request->user()->id;
        Log::notice('Impersonation started', ['admin' => $adminId, 'user' => $user->id, 'organization' => $organization->id]);

        Auth::login($user);
        $request->session()->put('impersonator_id', $adminId);
        $request->session()->put('impersonated_organization_id', $organization->id);

        return redirect()->route('dashboard');
    }

    /** Not behind the superadmin middleware: at this point the signed-in user is the tenant user. */
    public function stop(Request $request)
    {
        $adminId = $request->session()->pull('impersonator_id');
        $orgId = $request->session()->pull('impersonated_organization_id');

        $admin = $adminId ? User::where('id', $adminId)->where('is_super_admin', true)->first() : null;
        abort_unless($admin, 403);

        Log::notice('Impersonation ended', ['admin' => $admin->id, 'organization' => $orgId]);
        Auth::login($admin);

        return $orgId
            ? redirect()->route('admin.organizations.show', $orgId)
            : redirect()->route('admin.dashboard');
    }
}
