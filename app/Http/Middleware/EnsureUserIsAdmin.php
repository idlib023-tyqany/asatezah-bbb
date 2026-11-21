<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $attributes = $user?->getAttributes() ?? [];
        $isAdmin = $user
            && $user->isAdmin()
            && ($attributes['status'] ?? 'active') === 'active'
            && ! (bool) ($attributes['is_banned'] ?? false)
            && ($attributes['is_active'] ?? true)
            && ($attributes['deactivated_at'] ?? null) === null;

        if (! $isAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => 'غير مصرح لك بالوصول إلى لوحة الإدارة.',
            ], 403);
        }

        return $next($request);
    }
}
