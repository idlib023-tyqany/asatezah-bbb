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

        if (! $user || (! $user->isTeacher() && ! $user->isAdmin())) {
            return response()->json([
                'message' => 'هذا النظام مخصص لحسابات الأساتذة فقط.',
            ], 403);
        }

        return $next($request);
    }
}
