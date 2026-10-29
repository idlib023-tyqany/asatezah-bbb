<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportController extends Controller
{
    /**
     * List all reports with status filtering, pagination, and soft-deleted files support.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();

        // جلب العلاقات مع دعم الملفات المحذوفة مؤقتاً حتى لا يظهر الملف كـ null
        $query = Report::with([
            'user:id,name,email',
            'file' => fn ($q) => $q->withTrashed()->select('id', 'title', 'status', 'deleted_at'),
        ]);

        // دعم البحث النصي داخل البلاغات إذا وجد
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('reason', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%")
                    ->orWhere('admin_notes', 'like', "%{$q}%");
            });
        }

        if (in_array($status, [Report::STATUS_OPEN, Report::STATUS_RESOLVED, Report::STATUS_DISMISSED], true)) {
            $query->where('status', $status);
        } else {
            $query->where('status', Report::STATUS_OPEN);
        }

        $reports = $query->orderByDesc('created_at')->paginate($this->pageSize($request));

        return response()->json([
            'status' => 'success',
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Mark a report as resolved with optional admin notes and file take-down.
     */
    public function resolve(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'delete_file' => ['nullable', 'boolean'],
        ]);

        try {
            DB::transaction(function () use ($request, $report, $validated) {
                $report->update([
                    'status' => Report::STATUS_RESOLVED,
                    'admin_notes' => $validated['notes'] ?? $report->admin_notes,
                    'resolved_at' => now(),
                    'resolved_by' => $request->user()?->id,
                ]);

                if (! empty($validated['delete_file']) && $report->file && ! $report->file->trashed()) {
                    $report->file->delete();
                }
            });

            return response()->json([
                'message' => 'Report resolved successfully.',
                'data' => $report->fresh(['user', 'file']),
            ]);
        } catch (Throwable $e) {
            Log::error("ReportController: Failed to resolve report ID {$report->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to resolve report.',
            ], 500);
        }
    }

    /**
     * Dismiss a report (mark as invalid/rejected).
     */
    public function dismiss(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($request, $report, $validated) {
                $report->update([
                    'status' => Report::STATUS_DISMISSED,
                    'admin_notes' => $validated['notes'] ?? $report->admin_notes,
                    'resolved_at' => now(),
                    'resolved_by' => $request->user()?->id,
                ]);
            });

            return response()->json([
                'message' => 'Report dismissed successfully.',
                'data' => $report->fresh(['user', 'file']),
            ]);
        } catch (Throwable $e) {
            Log::error("ReportController: Failed to dismiss report ID {$report->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to dismiss report.',
            ], 500);
        }
    }

    /**
     * Delete a report log.
     */
    public function destroy(Report $report): JsonResponse
    {
        try {
            DB::transaction(function () use ($report) {
                $report->delete();
            });

            return response()->json(['message' => 'Report record deleted successfully.']);
        } catch (Throwable $e) {
            Log::error("ReportController: Failed to delete report record ID {$report->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to delete report record.',
            ], 500);
        }
    }

    /**
     * ميزة جديدة: الحذف الجماعي لسجلات البلاغات (Bulk Delete)
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:reports,id'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                Report::whereIn('id', $validated['ids'])->delete();
            });

            return response()->json(['message' => 'Selected reports deleted successfully.']);
        } catch (Throwable $e) {
            Log::error("ReportController: Failed to bulk delete reports. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to delete selected reports.',
            ], 500);
        }
    }

    /**
     * ميزة جديدة: إحصائيات البلاغات (Reports Statistics)
     */
    public function statistics(): JsonResponse
    {
        $stats = DB::table('reports')
            ->selectRaw('
                COUNT(*) as total_reports,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_reports,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved_reports,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dismissed_reports
            ', [Report::STATUS_OPEN, Report::STATUS_RESOLVED, Report::STATUS_DISMISSED])
            ->first();

        return response()->json([
            'total_reports' => (int) $stats->total_reports,
            'open_reports' => (int) $stats->open_reports,
            'resolved_reports' => (int) $stats->resolved_reports,
            'dismissed_reports' => (int) $stats->dismissed_reports,
        ]);
    }
}
