<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // While impersonating, the signed-in user is the tenant user: admin area is closed.
        abort_unless($request->user()?->isSuperAdmin() && ! $request->session()->has('impersonator_id'), 403);

        return $next($request);
    }
}
