<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\File;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorites_list_orders_by_favorite_time_and_includes_loaded_interactions(): void
    {
        $user = User::factory()->create();
        $file = File::create([
            'uploaded_by' => $user->id,
            'title' => 'Algebra notes',
            'filename' => 'algebra.pdf',
            'path' => 'files/algebra.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'content_hash' => hash('sha256', 'algebra'),
            'status' => File::STATUS_PUBLISHED,
        ]);
        Favorite::create([
            'user_id' => $user->id,
            'file_id' => $file->id,
        ]);
        Rating::create([
            'user_id' => $user->id,
            'file_id' => $file->id,
            'value' => 5,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('data.0.id', $file->id)
            ->assertJsonPath('data.0.is_favorite', true)
            ->assertJsonPath('data.0.my_rating', 5);
    }
}
