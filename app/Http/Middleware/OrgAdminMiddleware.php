<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrgAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_org_admin || !$user->organization_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
