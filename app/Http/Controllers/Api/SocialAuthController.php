<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FileStorage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function handleGoogleCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:8192'],
        ]);
        $clientId = config('services.google.client_id');

        if (! is_string($clientId) || $clientId === '') {
            return response()->json([
                'message' => 'Google sign-in is not configured on the server.',
            ], 503);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $validated['id_token'],
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Google sign-in could not reach the identity provider. Please try again.',
            ], 503);
        }

        if (! $response->successful()) {
            return response()->json(['message' => 'The Google sign-in token is invalid or expired.'], 401);
        }

        $claims = $response->json();
        $email = is_array($claims) && is_string($claims['email'] ?? null)
            ? mb_strtolower(trim($claims['email']))
            : '';
        $expiresAt = is_array($claims) ? filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT) : false;
        $issuer = is_array($claims) ? ($claims['iss'] ?? null) : null;

        if (
            ! is_array($claims)
            || ($claims['aud'] ?? null) !== $clientId
            || ! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ! in_array($claims['email_verified'] ?? null, ['true', true], true)
            || ! is_string($claims['sub'] ?? null)
            || $claims['sub'] === ''
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || $expiresAt === false
            || $expiresAt <= now()->timestamp
        ) {
            return response()->json([
                'message' => 'The Google account could not be verified. Check the OAuth client configuration.',
            ], 401);
        }

        $name = is_string($claims['name'] ?? null) && trim($claims['name']) !== ''
            ? trim($claims['name'])
            : Str::before($email, '@');

        return $this->createSession($email, $name);
    }

    public function handleFacebookCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string', 'max:8192'],
        ]);
        $appId = config('services.facebook.app_id');
        $clientSecret = config('services.facebook.client_secret');

        if (! is_string($appId) || $appId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            return response()->json([
                'message' => 'Facebook sign-in is not configured on the server.',
            ], 503);
        }

        $appAccessToken = $appId.'|'.$clientSecret;

        try {
            $debugResponse = Http::acceptJson()
                ->timeout(10)
                ->get('https://graph.facebook.com/debug_token', [
                    'input_token' => $validated['access_token'],
                    'access_token' => $appAccessToken,
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Facebook sign-in could not reach the identity provider. Please try again.',
            ], 503);
        }

        $tokenData = $debugResponse->json('data');
        $expiresAt = is_array($tokenData) ? filter_var($tokenData['expires_at'] ?? null, FILTER_VALIDATE_INT) : false;

        if (
            ! $debugResponse->successful()
            || ! is_array($tokenData)
            || ($tokenData['is_valid'] ?? false) !== true
            || (string) ($tokenData['app_id'] ?? '') !== $appId
            || ! is_string($tokenData['user_id'] ?? null)
            || $tokenData['user_id'] === ''
            || $expiresAt === false
            || ($expiresAt !== 0 && $expiresAt <= now()->timestamp)
        ) {
            return response()->json(['message' => 'The Facebook sign-in token is invalid or expired.'], 401);
        }

        try {
            $profileResponse = Http::acceptJson()
                ->timeout(10)
                ->get('https://graph.facebook.com/me', [
                    'fields' => 'id,name,email',
                    'access_token' => $validated['access_token'],
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Facebook sign-in could not reach the identity provider. Please try again.',
            ], 503);
        }

        $profile = $profileResponse->json();
        $email = is_array($profile) && is_string($profile['email'] ?? null)
            ? mb_strtolower(trim($profile['email']))
            : '';

        if (
            ! $profileResponse->successful()
            || ! is_array($profile)
            || (string) ($profile['id'] ?? '') !== $tokenData['user_id']
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            return response()->json([
                'message' => 'Facebook did not provide a verified email address for this account.',
            ], 422);
        }

        $name = is_string($profile['name'] ?? null) && trim($profile['name']) !== ''
            ? trim($profile['name'])
            : Str::before($email, '@');

        return $this->createSession($email, $name);
    }

    private function createSession(string $email, string $name): JsonResponse
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Str::random(64),
                'role' => User::ROLE_STUDENT,
                'can_contribute' => true,
            ],
        );
        $user->refresh();

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Your account has been suspended. Please contact support.',
            ], 403);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return response()->json([
            'message' => 'Signed in successfully.',
            'token' => $user->createToken('mobile-app')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'can_contribute' => $user->can_contribute,
                'specialization' => $user->specialization,
                'display_name' => $user->display_name,
                'phone_number' => $user->phone_number,
                'trainer_type' => $user->trainer_type,
                'avatar_url' => FileStorage::url($user->avatar_path),
            ],
        ]);
    }
}
