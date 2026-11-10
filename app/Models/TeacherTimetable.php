<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherTimetable extends Model
{
    protected $table = 'teacher_timetables';

    protected $fillable = [
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'grade_id',
        'subject_id',
        'section',
        'classroom',
        'reminder_minutes',
    ];

    protected function casts(): array
    {
        return [
            'teacher_id' => 'integer',
            'grade_id' => 'integer',
            'subject_id' => 'integer',
            'reminder_minutes' => 'integer',
        ];
    }
}
