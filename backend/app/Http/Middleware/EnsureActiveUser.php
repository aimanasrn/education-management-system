<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_active) {
            $request->user()?->tokens()->delete();
            abort(401, 'Unauthenticated.');
        }
        $request->user()->loadMissing('roleAssignments');

        return $next($request);
    }
}
