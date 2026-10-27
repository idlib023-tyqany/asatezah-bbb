<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_rate_a_published_file_and_update_their_rating(): void
    {
        $user = User::factory()->create();
        $file = File::create([
            'uploaded_by' => $user->id,
            'title' => 'Published notes',
            'filename' => 'notes.pdf',
            'path' => 'files/notes.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'content_hash' => hash('sha256', 'rating-notes'),
            'status' => File::STATUS_PUBLISHED,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/ratings/{$file->id}", [
                'value' => 4,
                'comment' => 'Useful material.',
            ])
            ->assertOk()
            ->assertJsonPath('my_rating', 4)
            ->assertJsonPath('ratings_count', 1);

        $this->postJson("/api/ratings/{$file->id}", ['value' => 5])
            ->assertOk()
            ->assertJsonPath('my_rating', 5)
            ->assertJsonPath('ratings_count', 1);

        $this->assertDatabaseCount('ratings', 1);
        $this->assertDatabaseHas('ratings', [
            'user_id' => $user->id,
            'file_id' => $file->id,
            'value' => 5,
            'comment' => null,
        ]);
        $this->assertSame(5.0, (float) $file->fresh()->avg_rating);
    }

    public function test_user_can_delete_their_rating(): void
    {
        $user = User::factory()->create();
        $file = File::create([
            'uploaded_by' => $user->id,
            'title' => 'Published notes',
            'filename' => 'notes.pdf',
            'path' => 'files/notes.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'content_hash' => hash('sha256', 'rating-delete'),
            'status' => File::STATUS_PUBLISHED,
        ]);
        Rating::create([
            'user_id' => $user->id,
            'file_id' => $file->id,
            'value' => 3,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/ratings/{$file->id}")
            ->assertOk()
            ->assertJsonPath('my_rating', null)
            ->assertJsonPath('ratings_count', 0);

        $this->assertDatabaseCount('ratings', 0);
        $this->assertSame(0.0, (float) $file->fresh()->avg_rating);
    }
}
