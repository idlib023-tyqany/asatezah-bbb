<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeacherGeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TeacherGeminiController extends Controller
{
    public function handle(Request $request, TeacherGeminiService $gemini): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'mode' => ['required', 'in:lesson_plan,exam,grading_scale,homework,solve,simplify,formula_sheet'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        try {
            return response()->json([
                'status' => 'success',
                'result' => $gemini->answer(
                    $validated['prompt'],
                    $validated['mode'],
                    $validated['folder_id'] ?? null,
                ),
            ]);
        } catch (Throwable) {
            return response()->json([
                'status' => 'error',
                'message' => 'تعذر معالجة الطلب عبر المساعد الذكي حالياً.',
            ], 502);
        }
    }
}
