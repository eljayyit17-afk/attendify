<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($user = $request->user()) && ! $request->routeIs('logout')) {
            abort_unless($user->is_active && ! $user->requires_password_setup, 403, 'Your account does not currently have access.');
        }

        return $next($request);
    }
}
