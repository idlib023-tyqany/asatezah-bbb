<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassProgress extends Model
{
    protected $table = 'class_progress';

    protected $fillable = [
        'teacher_id',
        'grade_id',
        'section',
        'last_topic',
        'page_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'teacher_id' => 'integer',
            'grade_id' => 'integer',
        ];
    }
}
