<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsTeacher
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $attributes = $user?->getAttributes() ?? [];

        if (! $user
            || (! $user->isTeacher() && ! $user->isAdmin())
            || ($attributes['status'] ?? 'active') !== 'active'
            || (bool) ($attributes['is_banned'] ?? false)
            || ! ($attributes['is_active'] ?? true)
            || ($attributes['deactivated_at'] ?? null) !== null) {
            return response()->json([
                'message' => 'هذا النظام مخصص لحسابات الأساتذة فقط.',
            ], 403);
        }

        return $next($request);
    }
}
