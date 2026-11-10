<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeacherGeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TeacherGeminiController extends Controller
{
    public function __construct(private readonly TeacherGeminiService $teacherGemini)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt'    => 'required|string',
            'mode'      => 'required|string',
            'folder_id' => 'nullable|integer',
        ]);

        try {
            return response()->json([
                'status' => 'success',
                'result' => $this->teacherGemini->answer(
                    $validated['prompt'],
                    $validated['mode'],
                    $validated['folder_id'] ?? null,
                ),
            ]);
        } catch (RuntimeException $e) {
            Log::warning('Teacher Gemini service is unavailable: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 503);
        } catch (ConnectionException|RequestException $e) {
            Log::error('Teacher Gemini request failed: '.$e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'تعذّر الاتصال بخدمة Gemini. يرجى المحاولة لاحقاً.',
            ], 503);
        }
    }
}
