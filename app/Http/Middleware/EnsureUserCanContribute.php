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

        if (! $user || ! $user->canContribute()) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، ليس لديك الصلاحية لرفع المواد أو المساهمة في المحتوى.',
            ], 403);
        }

        return $next($request);
    }
}
