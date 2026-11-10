<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassNote extends Model
{
    protected $table = 'class_notes';

    protected $fillable = [
        'teacher_id',
        'grade_id',
        'section',
        'note_content',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'teacher_id' => 'integer',
            'grade_id' => 'integer',
            'is_pinned' => 'boolean',
        ];
    }
}
