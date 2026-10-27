<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Return a bounded page size for list endpoints.
     */
    protected function pageSize(Request $request, int $default = 20): int
    {
        return min(100, max(1, $request->integer('per_page', $default)));
    }

    /**
     * إرجاع استجابة نجاح موحدة للـ API
     *
     * @param  mixed  $data  البيانات المراد إرسالها
     * @param  string|null  $message  رسالة توضيحية
     * @param  int  $code  رمز حالة HTTP
     */
    protected function successResponse(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * إرجاع استجابة خطأ موحدة للـ API
     *
     * @param  string  $message  رسالة الخطأ
     * @param  int  $code  رمز حالة HTTP
     * @param  mixed  $errors  تفاصيل الأخطاء (مثل أخطاء التحقق)
     */
    protected function errorResponse(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
