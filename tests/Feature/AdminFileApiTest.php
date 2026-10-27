<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reject_reopen_and_approve_a_file(): void
    {
        $admin = User::factory()->admin()->create();
        $uploader = User::factory()->teacher()->create();
        $file = File::create([
            'uploaded_by' => $uploader->id,
            'title' => 'Review notes',
            'filename' => 'review.pdf',
            'path' => 'files/review.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'content_hash' => hash('sha256', 'review-notes'),
            'status' => File::STATUS_PENDING,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/files/{$file->id}/reject", ['reason' => 'Unreadable scan.'])
            ->assertOk();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'status' => File::STATUS_REJECTED,
            'rejection_reason' => 'Unreadable scan.',
        ]);

        $this->postJson("/api/admin/files/{$file->id}/reopen")->assertOk();
        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'status' => File::STATUS_PENDING,
            'rejection_reason' => null,
        ]);

        $this->postJson("/api/admin/files/{$file->id}/approve")->assertOk();
        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'status' => File::STATUS_PUBLISHED,
        ]);
    }
}
