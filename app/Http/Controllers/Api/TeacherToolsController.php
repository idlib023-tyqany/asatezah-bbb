<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeacherToolsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherToolsController extends Controller
{
    public function __construct(private readonly TeacherToolsService $teacherTools)
    {
    }

    // --- جدول الحصص ---
    public function getTimetable(Request $request): JsonResponse
    {
        $teacherId = $request->user()->id;

        return response()->json([
            'status' => 'success',
            'data' => $this->teacherTools->timetableFor($teacherId),
        ]);
    }

    public function addTimetableSlot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'day_of_week'      => 'required|string',
            'start_time'       => 'required',
            'end_time'         => 'required',
            'grade_id'         => 'required|integer',
            'subject_id'       => 'required|integer',
            'section'          => 'required|string',
            'classroom'        => 'nullable|string',
            'reminder_minutes' => 'nullable|integer',
        ]);

        $slot = $this->teacherTools->addTimetableSlot(
            $request->user()->id,
            $data,
        );

        return response()->json([
            'status' => 'success',
            'id' => $slot->getKey(),
            'message' => 'تمت إضافة الحصة للجدول',
        ]);
    }

    // --- أين توقفت؟ (متتبع الشعب) ---
    public function getProgress(Request $request): JsonResponse
    {
        $teacherId = $request->user()->id;

        return response()->json([
            'status' => 'success',
            'data' => $this->teacherTools->progressFor($teacherId),
        ]);
    }

    public function updateProgress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grade_id'    => 'required|integer',
            'section'     => 'required|string',
            'last_topic'  => 'required|string',
            'page_number' => 'nullable|string',
        ]);

        $this->teacherTools->updateProgress(
            $request->user()->id,
            $data,
        );

        return response()->json(['status' => 'success', 'message' => 'تم حفظ آخر نقطة توقف']);
    }

    // --- المفكرة السريعة ---
    public function getNotes(Request $request): JsonResponse
    {
        $teacherId = $request->user()->id;

        return response()->json([
            'status' => 'success',
            'data' => $this->teacherTools->notesFor($teacherId),
        ]);
    }

    public function addNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grade_id'     => 'required|integer',
            'section'      => 'required|string',
            'note_content' => 'required|string',
        ]);

        $this->teacherTools->addNote(
            $request->user()->id,
            $data,
        );

        return response()->json(['status' => 'success', 'message' => 'تم حفظ الملاحظة']);
    }
}
