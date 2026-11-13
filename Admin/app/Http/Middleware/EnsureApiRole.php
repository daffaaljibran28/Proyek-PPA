<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(
            strtolower((string) $request->user()?->role) === strtolower($role),
            403
        );

        return $next($request);
    }
}
