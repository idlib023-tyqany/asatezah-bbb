<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherToolsController extends Controller
{
    // --- جدول الحصص ---
    public function getTimetable(Request $request)
    {
        $teacherId = $request->user()?->id ?? 1;
        $items = DB::table('teacher_timetables')
            ->join('grades', 'teacher_timetables.grade_id', '=', 'grades.id')
            ->join('subjects', 'teacher_timetables.subject_id', '=', 'subjects.id')
            ->where('teacher_timetables.teacher_id', $teacherId)
            ->select('teacher_timetables.*', 'grades.name as grade_name', 'subjects.name as subject_name')
            ->orderBy('start_time')
            ->get();

        return response()->json(['status' => 'success', 'data' => $items]);
    }

    public function addTimetableSlot(Request $request)
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

        $data['teacher_id'] = $request->user()?->id ?? 1;
        $data['created_at'] = now();

        $id = DB::table('teacher_timetables')->insertGetId($data);
        return response()->json(['status' => 'success', 'id' => $id, 'message' => 'تمت إضافة الحصة للجدول']);
    }

    // --- أين توقفت؟ (متتبع الشعب) ---
    public function getProgress(Request $request)
    {
        $teacherId = $request->user()?->id ?? 1;
        $progress = DB::table('class_progress')
            ->join('grades', 'class_progress.grade_id', '=', 'grades.id')
            ->where('class_progress.teacher_id', $teacherId)
            ->select('class_progress.*', 'grades.name as grade_name')
            ->latest('class_progress.updated_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $progress]);
    }

    public function updateProgress(Request $request)
    {
        $data = $request->validate([
            'grade_id'    => 'required|integer',
            'section'     => 'required|string',
            'last_topic'  => 'required|string',
            'page_number' => 'nullable|string',
        ]);

        $teacherId = $request->user()?->id ?? 1;
        DB::table('class_progress')->updateOrInsert(
            ['teacher_id' => $teacherId, 'grade_id' => $data['grade_id'], 'section' => $data['section']],
            ['last_topic' => $data['last_topic'], 'page_number' => $data['page_number'], 'updated_at' => now()]
        );

        return response()->json(['status' => 'success', 'message' => 'تم حفظ آخر نقطة توقف']);
    }

    // --- المفكرة السريعة ---
    public function getNotes(Request $request)
    {
        $teacherId = $request->user()?->id ?? 1;
        $notes = DB::table('class_notes')
            ->join('grades', 'class_notes.grade_id', '=', 'grades.id')
            ->where('class_notes.teacher_id', $teacherId)
            ->select('class_notes.*', 'grades.name as grade_name')
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'data' => $notes]);
    }

    public function addNote(Request $request)
    {
        $data = $request->validate([
            'grade_id'     => 'required|integer',
            'section'      => 'required|string',
            'note_content' => 'required|string',
        ]);

        $data['teacher_id'] = $request->user()?->id ?? 1;
        $data['created_at'] = now();

        DB::table('class_notes')->insert($data);
        return response()->json(['status' => 'success', 'message' => 'تم حفظ الملاحظة']);
    }
}
