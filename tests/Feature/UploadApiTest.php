<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
            ->assertJsonPath('data.file_type', 'application/pdf')
            ->assertJsonPath('data.file_size', 32768)
            ->assertJsonPath('data.cover_image_url', null);

        $file = File::query()->sole();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'uploaded_by' => $user->id,
            'content_hash' => $file->content_hash,
            'file_type' => 'application/pdf',
            'file_size' => 32768,
        ]);
        $this->assertTrue(Storage::disk()->exists($file->path));
        $this->assertStringStartsWith('documents/', $file->path);
        $this->assertSame(
            route('api.files.download', ['file' => $file->id]),
            $response->json('data.download_url')
        );
    }

    public function test_upload_can_be_associated_with_a_folder(): void
    {
        Storage::fake();
        $user = User::factory()->create(['can_contribute' => true]);
        $folder = Folder::create(['name' => 'Algebra']);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/upload', [
                'title' => 'Algebra notes',
                'folder_id' => $folder->id,
                'file' => UploadedFile::fake()->createWithContent(
                    'algebra.pdf',
                    'unique algebra file content',
                )->mimeType('application/pdf'),
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('files', [
            'title' => 'Algebra notes',
            'folder_id' => $folder->id,
        ]);
    }

    public function test_folder_endpoint_returns_its_published_files_as_json(): void
    {
        $user = User::factory()->create();
        $folder = Folder::create(['name' => 'Algebra']);
        $childFolder = Folder::create([
            'name' => 'Linear Algebra',
            'parent_id' => $folder->id,
        ]);

        $publishedFile = File::create([
            'uploaded_by' => $user->id,
            'folder_id' => $folder->id,
            'title' => 'Published notes',
            'filename' => 'published.pdf',
            'path' => 'documents/published.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'content_hash' => hash('sha256', 'published file'),
            'status' => File::STATUS_PUBLISHED,
        ]);
        File::create([
            'uploaded_by' => $user->id,
            'folder_id' => $folder->id,
            'title' => 'Pending notes',
            'filename' => 'pending.pdf',
            'path' => 'documents/pending.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'content_hash' => hash('sha256', 'pending file'),
            'status' => File::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/folders/{$folder->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Algebra')
            ->assertJsonPath('data.children.0.id', $childFolder->id)
            ->assertJsonPath('data.children.0.name', 'Linear Algebra')
            ->assertJsonCount(1, 'data.files')
            ->assertJsonPath('data.files.0.id', $publishedFile->id);

        $this->getJson('/api/folders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $folder->id)
            ->assertJsonPath('data.0.files_count', 1);
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

    public function test_telegram_download_url_uses_a_server_side_proxy(): void
    {
        config(['services.telegram.bot_token' => 'test-token']);
        Http::fake([
            'https://api.telegram.org/bottest-token/getFile*' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'documents/lesson.pdf'],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/files/telegram/sample-file-id/download-url');

        $response
            ->assertOk()
            ->assertJsonPath(
                'download_url',
                route('api.files.telegram.download', ['id' => 'sample-file-id'])
            )
            ->assertDontSee('test-token');

        Http::assertSent(fn ($request) => str_starts_with(
            $request->url(),
            'https://api.telegram.org/bottest-token/getFile?'
        )
            && $request['file_id'] === 'sample-file-id');
    }

    public function test_telegram_file_download_is_proxied_by_the_application(): void
    {
        config(['services.telegram.bot_token' => 'test-token']);
        Http::fake([
            'https://api.telegram.org/bottest-token/getFile*' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'documents/lesson.pdf'],
            ]),
            'https://api.telegram.org/file/bottest-token/documents/lesson.pdf' => Http::response(
                'lesson contents',
                200,
                ['Content-Type' => 'application/pdf']
            ),
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->get(route('api.files.telegram.download', ['id' => 'sample-file-id']))
            ->assertOk()
            ->assertDownload('lesson.pdf')
            ->assertStreamedContent('lesson contents');

        Http::assertSent(fn ($request) => $request->url()
            === 'https://api.telegram.org/file/bottest-token/documents/lesson.pdf');
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
