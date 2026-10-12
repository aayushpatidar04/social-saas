<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Guards the organization-facing application:
 *  - super admins have no organization: send them to the admin area
 *    (unless they are impersonating an organization user)
 *  - suspended / inactive / expired organizations see a notice instead of the app
 */
class EnsureTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $organization = $user->organization;

        if (! $user->is_active || ! $organization) {
            return $this->blocked($request, 'Your account is not active. Please contact your administrator.');
        }

        if (! $organization->isUsable()) {
            return $this->blocked($request, $organization->unusableReason());
        }

        return $next($request);
    }

    private function blocked(Request $request, string $reason)
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['error' => $reason], 403);
        }

        return Inertia::render('Suspended', ['reason' => $reason])->toResponse($request)->setStatusCode(403);
    }
}
