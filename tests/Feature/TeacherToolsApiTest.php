<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherToolsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_tools_require_an_authenticated_teacher(): void
    {
        $this->getJson('/api/timetable')->assertUnauthorized();
        $this->postJson('/api/ai/assist', [
            'prompt' => 'اكتب خطة درس.',
            'mode' => 'lesson_plan',
        ])->assertUnauthorized();
    }

    public function test_teacher_can_save_and_read_tools_for_their_classes(): void
    {
        $teacher = User::factory()->teacher()->create();
        $gradeId = $this->createGrade();
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'الرياضيات',
            'code' => 'MATH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/timetable', [
                'day_of_week' => 'sunday',
                'start_time' => '08:00',
                'end_time' => '08:45',
                'grade_id' => $gradeId,
                'subject_id' => $subjectId,
                'section' => 'أ',
                'classroom' => 'القاعة 4',
                'reminder_minutes' => 15,
            ])
            ->assertOk();

        $this->getJson('/api/timetable')
            ->assertOk()
            ->assertJsonPath('data.0.subject_name', 'الرياضيات');

        $this->postJson('/api/progress', [
            'grade_id' => $gradeId,
            'section' => 'أ',
            'last_topic' => 'المعادلات',
            'page_number' => '12',
        ])->assertOk();

        $this->getJson('/api/progress')
            ->assertOk()
            ->assertJsonPath('data.0.last_topic', 'المعادلات');

        $this->postJson('/api/notes', [
            'grade_id' => $gradeId,
            'section' => 'أ',
            'note_content' => 'مراجعة التمرين قبل الحصة القادمة.',
        ])->assertOk();

        $this->getJson('/api/notes')
            ->assertOk()
            ->assertJsonPath('data.0.note_content', 'مراجعة التمرين قبل الحصة القادمة.');
    }

    private function createGrade(): int
    {
        $stageId = DB::table('educational_stages')->insertGetId([
            'name' => 'المرحلة الأساسية',
            'order_index' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('grades')->insertGetId([
            'stage_id' => $stageId,
            'name' => 'الصف الثامن',
            'order_index' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
