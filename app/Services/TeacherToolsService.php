<?php

namespace App\Services;

use App\Models\ClassNote;
use App\Models\ClassProgress;
use App\Models\TeacherTimetable;
use Illuminate\Support\Collection;

class TeacherToolsService
{
    public function timetableFor(int $teacherId): Collection
    {
        return TeacherTimetable::query()
            ->join('grades', 'teacher_timetables.grade_id', '=', 'grades.id')
            ->join('subjects', 'teacher_timetables.subject_id', '=', 'subjects.id')
            ->where('teacher_timetables.teacher_id', $teacherId)
            ->select('teacher_timetables.*', 'grades.name as grade_name', 'subjects.name as subject_name')
            ->orderBy('start_time')
            ->get();
    }

    public function addTimetableSlot(int $teacherId, array $data): TeacherTimetable
    {
        return TeacherTimetable::query()->create([
            ...$data,
            'teacher_id' => $teacherId,
        ]);
    }

    public function progressFor(int $teacherId): Collection
    {
        return ClassProgress::query()
            ->join('grades', 'class_progress.grade_id', '=', 'grades.id')
            ->where('class_progress.teacher_id', $teacherId)
            ->select('class_progress.*', 'grades.name as grade_name')
            ->latest('class_progress.updated_at')
            ->get();
    }

    public function updateProgress(int $teacherId, array $data): ClassProgress
    {
        return ClassProgress::query()->updateOrCreate(
            [
                'teacher_id' => $teacherId,
                'grade_id' => $data['grade_id'],
                'section' => $data['section'],
            ],
            [
                'last_topic' => $data['last_topic'],
                'page_number' => $data['page_number'] ?? null,
            ],
        );
    }

    public function notesFor(int $teacherId): Collection
    {
        return ClassNote::query()
            ->join('grades', 'class_notes.grade_id', '=', 'grades.id')
            ->where('class_notes.teacher_id', $teacherId)
            ->select('class_notes.*', 'grades.name as grade_name')
            ->latest('class_notes.created_at')
            ->get();
    }

    public function addNote(int $teacherId, array $data): ClassNote
    {
        return ClassNote::query()->create([
            ...$data,
            'teacher_id' => $teacherId,
        ]);
    }
}
