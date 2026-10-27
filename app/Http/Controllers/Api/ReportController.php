<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    /**
     * Report a file for policy violation or issues.
     */
    public function store(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        $validated = $request->validate([
            'category' => [
                'required',
                'string',
                Rule::in(['copyright', 'inappropriate_content', 'incorrect_material', 'spam', 'other']),
            ],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ]);

        $user = $request->user();

        // حظر المستخدم المحظور من الإبلاغ إذا وُجدت الخاصية
        if (isset($user->can_contribute) && ! $user->can_contribute) {
            abort(403, 'Your account is restricted from submitting reports.');
        }

        DB::transaction(function () use ($validated, $user, $file, $request) {
            Report::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'file_id' => $file->id,
                ],
                [
                    'category' => $validated['category'],
                    'reason' => $validated['reason'] ?? $validated['category'],
                    'status' => Report::STATUS_OPEN,
                    'user_ip' => $request->ip(),
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Thank you. Your report has been submitted for review.',
        ], 201);
    }

    /**
     * Check if the user has already reported this file.
     */
    public function status(Request $request, File $file): JsonResponse
    {
        abort_unless($file->isPublished(), 404, 'File not found or not published.');

        $hasReported = Report::where('user_id', $request->user()->id)
            ->where('file_id', $file->id)
            ->exists();

        return response()->json([
            'has_reported' => $hasReported,
        ]);
    }
}
