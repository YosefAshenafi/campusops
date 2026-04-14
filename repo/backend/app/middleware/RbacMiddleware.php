<?php

namespace app\middleware;

use think\Request;
use think\Response;

class RbacMiddleware
{
    /**
     * Check if the authenticated user has the required permission.
     *
     * Usage in route definition:
     *   ->middleware('rbac:users.read')
     *   ->middleware('rbac:orders.refund')
     */
    public function handle(Request $request, \Closure $next, string $permission = ''): Response
    {
        $user = $request->user ?? null;

        if (!$user) {
            return json([
                'success' => false,
                'code' => 401,
                'error' => 'Authentication required',
            ], 401);
        }

        if (empty($permission)) {
            // No specific permission required, just auth
            return $next($request);
        }

        if (!$user->hasPermission($permission)) {
            $requestInfo = $request->method();
            try { $requestInfo .= ' ' . $request->path(); } catch (\Throwable $e) {}
            \think\facade\Log::warning("Permission denied: user {$user->id} ({$user->username}) denied '{$permission}' on {$requestInfo}");
            return json([
                'success' => false,
                'code' => 403,
                'error' => 'Insufficient permissions',
            ], 403);
        }

        return $next($request);
    }
}
