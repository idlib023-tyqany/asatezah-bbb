<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanContribute
{
    /**
     * Handle an incoming request.
     *
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
            || ($attributes['deactivated_at'] ?? null) !== null
            || ! $user->canContribute()) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، ليس لديك الصلاحية لرفع المواد أو المساهمة في المحتوى.',
            ], 403);
        }

        return $next($request);
    }
}
