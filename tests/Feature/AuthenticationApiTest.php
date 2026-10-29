<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token_and_user_payload(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'The provided credentials are incorrect.']);
    }

    public function test_login_normalizes_email_case(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => '  LOGIN@EXAMPLE.COM ',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'login@example.com');
    }

    public function test_register_persists_the_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'specialization' => 'Mathematics',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'specialization']]);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'specialization' => 'Mathematics',
        ]);
    }

    public function test_register_normalizes_email_and_rejects_case_insensitive_duplicates(): void
    {
        $this->postJson('/api/register', [
            'email' => '  New.User@Example.com ',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'new.user@example.com');

        $this->postJson('/api/register', [
            'email' => 'NEW.USER@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_saves_optional_profile_fields(): void
    {
        $this->postJson('/api/register', [
            'name' => 'New Teacher',
            'email' => 'teacher@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'display_name' => 'Teacher',
            'phone_number' => '+963900000000',
            'trainer_type' => 'subject_teacher',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'teacher@example.com',
            'display_name' => 'Teacher',
            'phone_number' => '+963900000000',
            'trainer_type' => 'subject_teacher',
        ]);
    }

    public function test_google_sign_in_verifies_the_provider_token_and_creates_an_account(): void
    {
        config(['services.google.client_id' => 'web-client-id']);
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'web-client-id',
                'iss' => 'https://accounts.google.com',
                'email' => 'Teacher@Example.com',
                'email_verified' => 'true',
                'sub' => 'google-user-123',
                'exp' => now()->addHour()->timestamp,
                'name' => 'Google Teacher',
            ]),
        ]);

        $response = $this->postJson('/api/auth/google', ['id_token' => 'valid-id-token']);

        $response
            ->assertOk()
            ->assertJsonPath('user.email', 'teacher@example.com')
            ->assertJsonPath('user.name', 'Google Teacher')
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        $this->assertDatabaseHas('users', [
            'email' => 'teacher@example.com',
            'email_verified_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_google_sign_in_rejects_a_token_for_a_different_oauth_client(): void
    {
        config(['services.google.client_id' => 'web-client-id']);
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'another-client-id',
                'iss' => 'https://accounts.google.com',
                'email' => 'teacher@example.com',
                'email_verified' => 'true',
                'sub' => 'google-user-123',
                'exp' => now()->addHour()->timestamp,
            ]),
        ]);

        $this->postJson('/api/auth/google', ['id_token' => 'invalid-id-token'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_facebook_sign_in_validates_the_app_token_and_creates_an_account(): void
    {
        config([
            'services.facebook.app_id' => 'facebook-app-id',
            'services.facebook.client_secret' => 'facebook-app-secret',
        ]);
        Http::fake([
            'graph.facebook.com/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'app_id' => 'facebook-app-id',
                    'user_id' => 'facebook-user-123',
                    'expires_at' => now()->addHour()->timestamp,
                ],
            ]),
            'graph.facebook.com/me*' => Http::response([
                'id' => 'facebook-user-123',
                'name' => 'Facebook Teacher',
                'email' => 'Teacher@Example.com',
            ]),
        ]);

        $this->postJson('/api/auth/facebook', ['access_token' => 'valid-access-token'])
            ->assertOk()
            ->assertJsonPath('user.email', 'teacher@example.com')
            ->assertJsonPath('user.name', 'Facebook Teacher')
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        $this->assertDatabaseHas('users', ['email' => 'teacher@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_facebook_sign_in_rejects_a_token_issued_to_another_app(): void
    {
        config([
            'services.facebook.app_id' => 'facebook-app-id',
            'services.facebook.client_secret' => 'facebook-app-secret',
        ]);
        Http::fake([
            'graph.facebook.com/debug_token*' => Http::response([
                'data' => [
                    'is_valid' => true,
                    'app_id' => 'another-facebook-app',
                    'user_id' => 'facebook-user-123',
                    'expires_at' => now()->addHour()->timestamp,
                ],
            ]),
        ]);

        $this->postJson('/api/auth/facebook', ['access_token' => 'wrong-app-token'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_authenticated_api_requests_accept_the_issued_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_protected_api_requests_require_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_password_change_revokes_all_sessions(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $firstToken = $user->createToken('first')->plainTextToken;
        $user->createToken('second');

        $this->withToken($firstToken)
            ->putJson('/api/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_all_revokes_every_session(): void
    {
        $user = User::factory()->create();
        $activeToken = $user->createToken('active')->plainTextToken;
        $user->createToken('other');

        $this->withToken($activeToken)
            ->postJson('/api/logout-all')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_tokens_are_rejected(): void
    {
        $this->withToken('invalid-token')->getJson('/api/user')->assertUnauthorized();
    }

    public function test_profile_avatar_uses_the_existing_avatar_column_and_public_disk(): void
    {
        Storage::fake();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->put('/api/profile/update', [
                'display_name' => 'Teacher',
                'avatar' => UploadedFile::fake()->createWithContent(
                    'avatar.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aV0cAAAAASUVORK5CYII='),
                ),
            ])
            ->assertOk()
            ->assertJsonPath('user.display_name', 'Teacher');

        $avatarPath = $user->fresh()->avatar_path;

        $this->assertNotNull($avatarPath);
        $this->assertTrue(Storage::disk()->exists($avatarPath));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar' => $avatarPath,
        ]);
    }
}
