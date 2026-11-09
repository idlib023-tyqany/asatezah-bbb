<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sms.endpoint' => 'https://sms.example.test/messages',
            'services.sms.token' => 'test-token',
        ]);
        Http::fake();
        Notification::fake();
    }

    public function test_login_requires_both_codes_before_returning_a_token(): void
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
            ->assertJsonPath('purpose', 'login')
            ->assertJsonPath('channels', ['email', 'phone'])
            ->assertJsonMissingPath('token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('verification_challenges', 1);
        Notification::assertSentOnDemand(VerificationCodeNotification::class);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://sms.example.test/messages');

        $this->postJson('/api/verification/confirm', [
            'verification_id' => $response->json('verification_id'),
            'email_code' => $this->sentEmailCode(),
            'phone_code' => $this->sentPhoneCode(),
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['email', 'phone_number']]);

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

    public function test_social_auth_routes_are_not_available(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        foreach ([
            'api/auth/google',
            'api/auth/facebook',
        ] as $uri) {
            $this->assertFalse($routes->contains(
                fn ($route): bool => $route->uri() === $uri
                    && in_array('POST', $route->methods(), true)
            ));
        }
    }

    public function test_users_table_does_not_have_social_auth_columns(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'provider'));
        $this->assertFalse(Schema::hasColumn('users', 'provider_id'));
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
            ->assertJsonPath('purpose', 'login');

        $this->assertDatabaseHas('verification_challenges', ['email' => 'login@example.com']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_register_persists_the_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'phone_number' => '+963912345678',
            'password' => 'password',
            'password_confirmation' => 'password',
            'specialization' => 'Mathematics',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('purpose', 'register')
            ->assertJsonPath('channels', ['email', 'phone'])
            ->assertJsonMissingPath('token');

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'phone_number' => '+963912345678',
            'role' => User::ROLE_TEACHER,
            'specialization' => 'Mathematics',
            'is_active' => false,
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_register_normalizes_email_and_rejects_case_insensitive_duplicates(): void
    {
        $this->postJson('/api/register', [
            'email' => '  New.User@Example.com ',
            'phone_number' => '+963912345678',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertCreated()
            ->assertJsonPath('purpose', 'register');

        $this->assertDatabaseHas('users', ['email' => 'new.user@example.com']);

        $this->postJson('/api/register', [
            'email' => 'NEW.USER@example.com',
            'phone_number' => '+963912345679',
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
            'phone_number' => '+963912345678',
            'password' => 'password',
            'password_confirmation' => 'password',
            'display_name' => 'Teacher',
            'trainer_type' => 'subject_teacher',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'teacher@example.com',
            'display_name' => 'Teacher',
            'phone_number' => '+963912345678',
            'trainer_type' => 'subject_teacher',
        ]);
    }

    public function test_registration_issues_a_token_only_after_both_codes_are_verified(): void
    {
        $registration = $this->postJson('/api/register', [
            'email' => 'verified@example.com',
            'phone_number' => '+963912345678',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertCreated();

        $emailCode = $this->sentEmailCode();
        $phoneCode = $this->sentPhoneCode();

        $this->postJson('/api/verification/confirm', [
            'verification_id' => $registration->json('verification_id'),
            'email_code' => $emailCode,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/verification/confirm', [
            'verification_id' => $registration->json('verification_id'),
            'email_code' => $emailCode,
            'phone_code' => $phoneCode,
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['email_verified', 'phone_verified']])
            ->assertJsonPath('user.email_verified', true)
            ->assertJsonPath('user.phone_verified', true);

        $this->assertDatabaseHas('users', [
            'email' => 'verified@example.com',
            'is_active' => true,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('verification_challenges', 0);
    }

    public function test_authenticated_api_requests_accept_the_issued_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_protected_api_requests_require_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_library_routes_require_an_authenticated_teacher_account(): void
    {
        $this->getJson('/api/files')->assertUnauthorized();

        $this->actingAs(User::factory()->teacher()->create(), 'sanctum')
            ->getJson('/api/files')
            ->assertOk();
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
        $user = User::factory()->create(['email' => 'login@example.com']);
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

    public function test_profile_contact_changes_are_not_saved_until_the_new_destination_is_verified(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com']);
        $token = $user->createToken('test-token')->plainTextToken;

        $challenge = $this->withToken($token)->postJson('/api/profile/contact-change', [
            'email' => 'updated@example.com',
        ])->assertAccepted()
            ->assertJsonPath('channels', ['email']);

        $this->assertSame('login@example.com', $user->fresh()->email);
        $emailCode = $this->sentEmailCode();

        $this->withToken($token)->postJson('/api/profile/contact-change/verify', [
            'verification_id' => $challenge->json('verification_id'),
            'email_code' => $emailCode,
        ])->assertOk()
            ->assertJsonPath('user.email', 'updated@example.com')
            ->assertJsonPath('user.email_verified', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
        ]);
    }

    public function test_profile_phone_changes_are_not_saved_until_the_new_number_is_verified(): void
    {
        $user = User::factory()->create(['phone_number' => '+963900000001']);
        $token = $user->createToken('test-token')->plainTextToken;

        $challenge = $this->withToken($token)->postJson('/api/profile/contact-change', [
            'phone_number' => '+963900000002',
        ])->assertAccepted()
            ->assertJsonPath('channels', ['phone']);

        $phoneCode = $this->sentPhoneCode();

        $this->withToken($token)->postJson('/api/profile/contact-change/verify', [
            'verification_id' => $challenge->json('verification_id'),
            'phone_code' => $phoneCode,
        ])->assertOk()
            ->assertJsonPath('user.phone_number', '+963900000002')
            ->assertJsonPath('user.phone_verified', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone_number' => '+963900000002',
        ]);
    }

    public function test_profile_email_and_phone_cannot_bypass_contact_verification(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com']);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->putJson('/api/profile/update', [
            'email' => 'unverified@example.com',
            'phone_number' => '+963900000002',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone_number']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'login@example.com',
            'phone_number' => $user->phone_number,
        ]);
    }

    private function sentEmailCode(): string
    {
        $code = null;

        Notification::assertSentOnDemand(
            VerificationCodeNotification::class,
            function (VerificationCodeNotification $notification) use (&$code): bool {
                $code = $notification->code;

                return true;
            },
        );

        $this->assertNotNull($code);

        return $code;
    }

    private function sentPhoneCode(): string
    {
        $code = null;

        Http::assertSent(function ($request) use (&$code): bool {
            preg_match('/(\d{6})/', $request['message'], $matches);
            $code = $matches[1] ?? null;

            return true;
        });

        $this->assertNotNull($code);

        return $code;
    }
}
