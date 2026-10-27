<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_and_update_a_report_without_changing_the_file_counter(): void
    {
        $user = User::factory()->create();
        $file = File::create([
            'uploaded_by' => $user->id,
            'title' => 'Published notes',
            'filename' => 'notes.pdf',
            'path' => 'files/notes.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'content_hash' => hash('sha256', 'notes'),
            'status' => File::STATUS_PUBLISHED,
        ]);

        $payload = [
            'category' => 'copyright',
            'reason' => 'This document appears to be copied.',
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/reports/{$file->id}", $payload)
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->postJson("/api/reports/{$file->id}", [
            'category' => 'other',
            'reason' => 'There is an issue with this document.',
        ])->assertCreated();

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', [
            'user_id' => $user->id,
            'file_id' => $file->id,
            'category' => 'other',
            'status' => Report::STATUS_OPEN,
        ]);
    }
}
