<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController extends Controller
{
    /**
     * Register a new user and return an API token.
     */
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'specialization' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30', 'unique:users,phone_number'],
            'trainer_type' => ['nullable', 'in:class_teacher,subject_teacher'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            // إرجاع الكائن مباشرة من المعاملة لضمان توفره خارجها
            $user = DB::transaction(function () use ($validated) {
                return User::create([
                    'name' => $validated['name'] ?? strtok($validated['email'], '@'),
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'specialization' => $validated['specialization'] ?? null,
                    'role' => User::ROLE_STUDENT,
                    'can_contribute' => true,
                    'display_name' => $validated['display_name'] ?? null,
                    'phone_number' => $validated['phone_number'] ?? null,
                    'trainer_type' => $validated['trainer_type'] ?? null,
                ]);
            });

            $tokenName = $validated['device_name'] ?? 'mobile-app';

            return response()->json([
                'message' => 'Account registered successfully.',
                'token' => $user->createToken($tokenName)->plainTextToken,
                'user' => $this->userPayload($user),
            ], 201);
        } catch (Throwable $e) {
            Log::error("AuthController: Registration failed. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to register account.',
            ], 500);
        }
    }

    /**
     * Authenticate a user and return an API token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // التحقق من صحة بيانات الدخول
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        // التحقق من أن الحساب ليس محظوراً
        if (isset($user->is_active) && ! $user->is_active) {
            return response()->json([
                'message' => 'Your account has been suspended. Please contact support.',
            ], 403);
        }

        $tokenName = $validated['device_name'] ?? 'mobile-app';

        return response()->json([
            'message' => 'Logged in successfully.',
            'token' => $user->createToken($tokenName)->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Get current authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Revoke the current user's active token.
     */
    public function logout(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            $request->user()->currentAccessToken()->delete();
        });

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Revoke all user tokens across all devices.
     */
    public function logoutAllDevices(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            $request->user()->tokens()->delete();
        });

        return response()->json(['message' => 'Logged out from all devices.']);
    }

    /**
     * Update user profile & avatar.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone_number')->ignore($user->id)],
            'trainer_type' => ['nullable', 'in:class_teacher,subject_teacher'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $newAvatarPath = null;
        $oldAvatarPath = $user->avatar_path;

        try {
            if ($request->hasFile('avatar')) {
                $uploadedAvatarPath = FileStorage::store($request->file('avatar'), 'avatars');
                $newAvatarPath = $uploadedAvatarPath;
                unset($validated['avatar']);
                $validated['avatar_path'] = $newAvatarPath;
            }

            DB::transaction(function () use ($user, $validated) {
                $user->fill($validated);
                $user->save();
            });

            $disk = FileStorage::disk();
            if (
                $newAvatarPath !== null
                && ! empty($oldAvatarPath)
                && $disk->exists($oldAvatarPath)
                && ! $disk->delete($oldAvatarPath)
            ) {
                Log::warning("AuthController: Failed to remove replaced avatar for user ID {$user->id}.");
            }

            return response()->json([
                'message' => 'Profile updated successfully.',
                'user' => $this->userPayload($user->fresh()),
            ]);
        } catch (Throwable $e) {
            if ($newAvatarPath !== null && ! FileStorage::disk()->delete($newAvatarPath)) {
                Log::error("AuthController: Failed to clean up uploaded avatar for user ID {$user->id}.");
            }

            Log::error("AuthController: Failed to update profile for user ID {$user->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to update profile.',
            ], 500);
        }
    }

    /**
     * Update user password safely.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        try {
            DB::transaction(function () use ($user, $validated) {
                $user->update([
                    'password' => Hash::make($validated['password']),
                ]);

                // إلغاء جميع التوكنات للأمان عند تغيير كلمة المرور
                $user->tokens()->delete();
            });

            return response()->json(['message' => 'Password changed successfully. All other sessions have been logged out.']);
        } catch (Throwable $e) {
            Log::error("AuthController: Failed to update password for user ID {$user->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to update password.',
            ], 500);
        }
    }

    /**
     * Delete the authenticated user's account permanently.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Incorrect password.'], 422);
        }

        try {
            DB::transaction(function () use ($user) {
                // إزالة الصورة وملفات التوكنات وحذف الحساب نهائياً
                if (! empty($user->avatar_path) && FileStorage::disk()->exists($user->avatar_path)) {
                    FileStorage::disk()->delete($user->avatar_path);
                }

                $user->tokens()->delete();
                $user->delete();
            });

            return response()->json(['message' => 'Account deleted successfully.']);
        } catch (Throwable $e) {
            Log::error("AuthController: Failed to delete account ID {$user->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to delete account.',
            ], 500);
        }
    }

    private function userPayload(User $user): array
    {
        // استخدام asset() لضمان توليد URL كامل ومستقر لصورة الـ Avatar لتطبيق Flutter
        $avatarUrl = FileStorage::url($user->avatar_path);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'can_contribute' => $user->can_contribute,
            'specialization' => $user->specialization,
            'display_name' => $user->display_name,
            'phone_number' => $user->phone_number,
            'trainer_type' => $user->trainer_type,
            'avatar_url' => $avatarUrl,
        ];
    }
}
