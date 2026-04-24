<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChangeCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! config('grom_access.require_password_change')
            || ! $user
            || ! $user->must_change_password
            || $request->routeIs('password.*')
        ) {
            return $next($request);
        }

        return redirect()->route('password.edit');
    }
}
