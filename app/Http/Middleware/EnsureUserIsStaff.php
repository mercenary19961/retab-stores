<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the back-office (/admin/*) to staff — admin or editor roles.
 * Storefront customers (or guests) get a 403.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isStaff(), 403);

        return $next($request);
    }
}
