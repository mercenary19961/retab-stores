<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for the admin panel. Usage: middleware('permission:orders.export').
 * Admins always pass; editors must hold the "section.action" permission.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission($permission)) {
            abort(403, __('messages.admin.no_permission'));
        }

        return $next($request);
    }
}
