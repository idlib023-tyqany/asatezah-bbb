<?php

use App\Http\Middleware\EnsureUserCanContribute;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // تسجيل اختصارات الميدلوير المخصصة لحماية مسارات الـ API
        $middleware->alias([
            'admin'          => EnsureUserIsAdmin::class,
            'can_contribute' => EnsureUserCanContribute::class,
        ]);

        $middleware->throttleApi();

        // ضمان تطبيق CORS والحماية على كافة مسارات الـ API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // معالجة خطأ عدم العثور على المسار (404) لاستجابة API نظيفة
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'المورد المطلوب غير موجود أو تم حذفه.',
                ], 404);
            }
        });

        // معالجة خطأ عدم عثور Eloquent على النموذج (404)
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'المورد المطلوب غير موجود.',
                ], 404);
            }
        });

        // معالجة خطأ التوثيق والمصادقة (401 Unauthenticated)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بالوصول، يرجى تسجيل الدخول أولاً.',
                ], 401);
            }
        });
    })->create();
