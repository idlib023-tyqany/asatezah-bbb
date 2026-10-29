<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_uploads_a_file_for_review(): void
    {
        Storage::fake();
        $user = User::factory()->create(['can_contribute' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/upload', [
                'title' => 'Algebra notes',
                'description' => 'First chapter.',
                'file' => UploadedFile::fake()->create('algebra.pdf', 32, 'application/pdf'),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'Algebra notes')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.cover_image_url', null);

        $file = File::query()->sole();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'uploaded_by' => $user->id,
            'content_hash' => $file->content_hash,
        ]);
        $this->assertTrue(Storage::disk()->exists($file->path));
        $this->assertStringStartsWith('documents/', $file->path);
        $this->assertSame(
            route('api.files.download', ['file' => $file->id]),
            $response->json('data.download_url')
        );
    }

    public function test_uploads_are_stored_in_a_folder_based_on_their_mime_type(): void
    {
        Storage::fake();
        $user = User::factory()->create(['can_contribute' => true]);

        $uploads = [
            [
                'title' => 'Lesson video',
                'file' => UploadedFile::fake()
                    ->createWithContent('lesson.mp4', 'unique video content')
                    ->mimeType('video/mp4'),
                'folder' => 'videos',
            ],
            [
                'title' => 'Course notes',
                'file' => UploadedFile::fake()
                    ->createWithContent('notes.pdf', 'unique document content')
                    ->mimeType('application/pdf'),
                'folder' => 'documents',
            ],
            [
                'title' => 'Course cover',
                'file' => UploadedFile::fake()->createWithContent(
                    'cover.png',
                    'unique image content',
                )->mimeType('image/png'),
                'folder' => 'images',
            ],
        ];

        foreach ($uploads as $upload) {
            $response = $this->actingAs($user, 'sanctum')
                ->post('/api/upload', [
                    'title' => $upload['title'],
                    'file' => $upload['file'],
                ]);

            $response->assertCreated();
            $path = File::query()->where('title', $upload['title'])->value('path');
            $this->assertStringStartsWith($upload['folder'].'/', $path);
            $this->assertTrue(Storage::disk()->exists($path));
        }

        $this->assertDatabaseCount('files', count($uploads));
    }

    public function test_published_file_download_streams_from_the_configured_disk(): void
    {
        Storage::fake();
        $user = User::factory()->create();
        $path = 'documents/lesson.pdf';
        Storage::disk()->put($path, 'downloadable file content');

        $file = File::create([
            'uploaded_by' => $user->id,
            'title' => 'Lesson notes',
            'filename' => 'lesson.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 25,
            'content_hash' => hash('sha256', 'downloadable file content'),
            'status' => File::STATUS_PUBLISHED,
        ]);

        $this->actingAs($user, 'sanctum')
            ->get(route('api.files.download', ['file' => $file->id]))
            ->assertOk()
            ->assertDownload('lesson.pdf')
            ->assertStreamedContent('downloadable file content');

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'downloads_count' => 1,
        ]);
    }

    public function test_upload_rejects_unsupported_file_types(): void
    {
        Storage::fake();
        $user = User::factory()->create(['can_contribute' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/upload', [
                'title' => 'Executable',
                'file' => UploadedFile::fake()->create('program.exe', 32, 'application/x-msdownload'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('files', 0);
        $this->assertSame([], Storage::disk()->allFiles());
    }

    public function test_user_without_upload_permission_is_rejected(): void
    {
        Storage::fake();
        $user = User::factory()->create(['can_contribute' => false]);

        $this->actingAs($user, 'sanctum')
            ->post('/api/upload', [
                'title' => 'Algebra notes',
                'file' => UploadedFile::fake()->create('algebra.pdf', 32, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('files', 0);
        $this->assertSame([], Storage::disk()->allFiles());
    }

    public function test_upload_endpoint_is_rate_limited(): void
    {
        $user = User::factory()->create(['can_contribute' => true]);
        $this->actingAs($user, 'sanctum');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/upload', [])->assertUnprocessable();
        }

        $this->postJson('/api/upload', [])->assertTooManyRequests();
    }
}
