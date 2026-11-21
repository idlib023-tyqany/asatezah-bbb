<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTeacherIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $attributes = $user?->getAttributes() ?? [];
        if (! $user
            || ($attributes['status'] ?? 'active') !== 'active'
            || (bool) ($attributes['is_banned'] ?? false)
            || ! ($attributes['is_active'] ?? true)
            || ($attributes['deactivated_at'] ?? null) !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'حسابك غير مفعل أو محظور، يرجى التواصل مع الإدارة.',
            ], 403);
        }

        return $next($request);
    }
}
