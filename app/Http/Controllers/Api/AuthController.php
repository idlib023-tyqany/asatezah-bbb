<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationChallenge;
use App\Services\VerificationChallengeService;
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
     * Register an inactive user and send both verification codes.
     */
    public function register(Request $request, VerificationChallengeService $challenges): JsonResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'phone_number' => $request->filled('phone_number')
                ? $this->normalizePhone((string) $request->input('phone_number'))
                : null,
        ]);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'specialization' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/', 'unique:users,phone_number'],
            'trainer_type' => ['nullable', 'in:class_teacher,subject_teacher'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(fn (): User => User::create([
            'name' => $validated['name'] ?? strtok($validated['email'], '@'),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'specialization' => $validated['specialization'] ?? null,
            'role' => User::ROLE_STUDENT,
            'can_contribute' => true,
            'display_name' => $validated['display_name'] ?? null,
            'phone_number' => $validated['phone_number'],
            'trainer_type' => $validated['trainer_type'] ?? null,
            'is_active' => false,
        ]));

        try {
            $challenge = $challenges->issue($user, 'register', ['email', 'phone']);
        } catch (Throwable $exception) {
            $user->delete();
            Log::error("AuthController: Registration verification delivery failed. Error: {$exception->getMessage()}");

            return response()->json([
                'message' => 'تعذّر إرسال رموز التحقق. تحقق من إعدادات البريد والرسائل النصية ثم أعد المحاولة.',
            ], 503);
        }

        return response()->json([
            'message' => 'أُنشئ الحساب؛ أكّد البريد والهاتف للمتابعة.',
            ...$challenges->response($challenge),
        ], 201);
    }

    /**
     * Authenticate credentials and start a dual-channel verification challenge.
     */
    public function login(Request $request, VerificationChallengeService $challenges): JsonResponse
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

        if ($user->deactivated_at !== null
            || (! $user->is_active && ($user->email_verified_at || $user->phone_verified_at))) {
            return response()->json([
                'message' => 'Your account has been suspended. Please contact support.',
            ], 403);
        }

        if (! $user->phone_number) {
            return response()->json([
                'message' => 'يجب إضافة رقم هاتف للحساب قبل تسجيل الدخول. تواصل مع الدعم.',
            ], 403);
        }

        try {
            $challenge = $challenges->issue($user, 'login', ['email', 'phone']);
        } catch (Throwable $exception) {
            Log::error("AuthController: Login verification delivery failed for user ID {$user->id}. Error: {$exception->getMessage()}");

            return response()->json([
                'message' => 'تعذّر إرسال رموز التحقق. تحقق من إعدادات البريد والرسائل النصية ثم أعد المحاولة.',
            ], 503);
        }

        return response()->json([
            'message' => 'أُرسلت رموز التحقق إلى البريد والهاتف.',
            ...$challenges->response($challenge),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_id' => ['required', 'uuid'],
            'email_code' => ['nullable', 'digits:6'],
            'phone_code' => ['nullable', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);
        $challenge = VerificationChallenge::findOrFail($validated['verification_id']);

        if (! in_array($challenge->purpose, ['register', 'login'], true)) {
            return response()->json(['message' => 'رمز التحقق غير صالح لهذا الطلب.'], 422);
        }

        $result = DB::transaction(function () use ($validated): array {
            $challenge = VerificationChallenge::query()
                ->whereKey($validated['verification_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($challenge->expires_at->isPast()) {
                return ['status' => 'expired'];
            }
            if ($challenge->attempts >= 5) {
                return ['status' => 'locked'];
            }
            if (! $this->codesMatch($challenge, $validated)) {
                $challenge->increment('attempts');

                return ['status' => 'invalid'];
            }

            $user = User::query()->lockForUpdate()->findOrFail($challenge->user_id);
            if ($user->deactivated_at !== null
                || (! $user->is_active && ($user->email_verified_at || $user->phone_verified_at))) {
                return ['status' => 'blocked'];
            }
            $user->email_verified_at ??= now();
            $user->phone_verified_at ??= now();
            $user->is_active = true;
            $user->save();
            $token = $user->createToken($validated['device_name'] ?? 'mobile-app')->plainTextToken;
            $challenge->delete();

            return [
                'status' => 'verified',
                'token' => $token,
                'user' => $this->userPayload($user),
            ];
        });

        if ($result['status'] !== 'verified') {
            return $this->verificationError($result['status']);
        }

        return response()->json([
            'message' => 'تم تأكيد البريد والهاتف بنجاح.',
            'token' => $result['token'],
            'user' => $result['user'],
        ]);
    }

    public function resendVerification(Request $request, VerificationChallengeService $challenges): JsonResponse
    {
        $validated = $request->validate(['verification_id' => ['required', 'uuid']]);
        $challenge = VerificationChallenge::findOrFail($validated['verification_id']);

        if (! in_array($challenge->purpose, ['register', 'login'], true)) {
            return response()->json(['message' => 'رمز التحقق غير صالح لهذا الطلب.'], 422);
        }
        if ($challenge->resends >= 3) {
            return response()->json(['message' => 'تم تجاوز عدد مرات إعادة إرسال الرمز.'], 429);
        }

        try {
            $challenges->resend($challenge);
        } catch (Throwable $exception) {
            Log::error("AuthController: Verification resend failed for challenge {$challenge->id}. Error: {$exception->getMessage()}");

            return response()->json([
                'message' => 'تعذّر إعادة إرسال رموز التحقق. حاول لاحقاً.',
            ], 503);
        }

        return response()->json([
            'message' => 'أُعيد إرسال رموز التحقق.',
            ...$challenges->response($challenge->fresh()),
        ]);
    }

    /**
     * Request verification before changing one or both contact destinations.
     */
    public function requestContactChange(Request $request, VerificationChallengeService $challenges): JsonResponse
    {
        $user = $request->user();
        $normalized = [];
        if ($request->has('email')) {
            $normalized['email'] = mb_strtolower(trim((string) $request->input('email')));
        }
        if ($request->has('phone_number')) {
            $normalized['phone_number'] = $this->normalizePhone((string) $request->input('phone_number'));
        }
        $request->merge($normalized);

        $validated = $request->validate([
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['sometimes', 'required', 'string', 'regex:/^\+?[1-9]\d{6,14}$/', Rule::unique('users', 'phone_number')->ignore($user->id)],
        ]);

        $channels = [];
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $channels[] = 'email';
        }
        if (isset($validated['phone_number']) && $validated['phone_number'] !== $user->phone_number) {
            $channels[] = 'phone';
        }
        if ($channels === []) {
            return response()->json(['message' => 'أدخل بريداً أو رقماً جديداً مختلفاً عن بياناتك الحالية.'], 422);
        }

        $challengeUser = clone $user;
        if (in_array('email', $channels, true)) {
            $challengeUser->email = $validated['email'];
        }
        if (in_array('phone', $channels, true)) {
            $challengeUser->phone_number = $validated['phone_number'];
        }

        try {
            $challenge = $challenges->issue($challengeUser, 'contact_change', $channels);
        } catch (Throwable $exception) {
            Log::error("AuthController: Contact verification delivery failed for user ID {$user->id}. Error: {$exception->getMessage()}");

            return response()->json([
                'message' => 'تعذّر إرسال رمز التحقق إلى بيانات التواصل الجديدة.',
            ], 503);
        }

        return response()->json([
            'message' => 'أُرسل رمز التحقق إلى بيانات التواصل الجديدة.',
            ...$challenges->response($challenge),
        ], 202);
    }

    public function verifyContactChange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_id' => ['required', 'uuid'],
            'email_code' => ['nullable', 'digits:6'],
            'phone_code' => ['nullable', 'digits:6'],
        ]);
        $user = $request->user();

        $result = DB::transaction(function () use ($validated, $user): array {
            $challenge = VerificationChallenge::query()
                ->whereKey($validated['verification_id'])
                ->where('user_id', $user->id)
                ->where('purpose', 'contact_change')
                ->lockForUpdate()
                ->firstOrFail();

            if ($challenge->expires_at->isPast()) {
                return ['status' => 'expired'];
            }
            if ($challenge->attempts >= 5) {
                return ['status' => 'locked'];
            }
            if (! $this->codesMatch($challenge, $validated)) {
                $challenge->increment('attempts');

                return ['status' => 'invalid'];
            }

            if (in_array('email', $challenge->channels, true)) {
                $user->email = $challenge->email;
                $user->email_verified_at = now();
            }
            if (in_array('phone', $challenge->channels, true)) {
                $user->phone_number = $challenge->phone_number;
                $user->phone_verified_at = now();
            }
            $user->save();
            $challenge->delete();

            return ['status' => 'verified', 'user' => $this->userPayload($user)];
        });

        if ($result['status'] !== 'verified') {
            return $this->verificationError($result['status']);
        }

        return response()->json([
            'message' => 'تم تحديث بيانات التواصل بعد التحقق.',
            'user' => $result['user'],
        ]);
    }

    public function resendContactChange(Request $request, VerificationChallengeService $challenges): JsonResponse
    {
        $validated = $request->validate(['verification_id' => ['required', 'uuid']]);
        $challenge = VerificationChallenge::query()
            ->whereKey($validated['verification_id'])
            ->where('user_id', $request->user()->id)
            ->where('purpose', 'contact_change')
            ->firstOrFail();
        if ($challenge->resends >= 3) {
            return response()->json(['message' => 'تم تجاوز عدد مرات إعادة إرسال الرمز.'], 429);
        }

        try {
            $challenges->resend($challenge);
        } catch (Throwable $exception) {
            Log::error("AuthController: Contact verification resend failed for challenge {$challenge->id}. Error: {$exception->getMessage()}");

            return response()->json([
                'message' => 'تعذّر إعادة إرسال رمز التحقق. حاول لاحقاً.',
            ], 503);
        }

        return response()->json([
            'message' => 'أُعيد إرسال رمز التحقق.',
            ...$challenges->response($challenge->fresh()),
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
            'email' => ['prohibited'],
            'phone_number' => ['prohibited'],
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
            'email_verified' => $user->email_verified_at !== null,
            'role' => $user->role,
            'can_contribute' => $user->can_contribute,
            'specialization' => $user->specialization,
            'display_name' => $user->display_name,
            'phone_number' => $user->phone_number,
            'phone_verified' => $user->phone_verified_at !== null,
            'trainer_type' => $user->trainer_type,
            'avatar_url' => $avatarUrl,
        ];
    }

    private function codesMatch(VerificationChallenge $challenge, array $codes): bool
    {
        if (in_array('email', $challenge->channels, true)
            && (! isset($codes['email_code']) || $challenge->email_code_hash === null
                || ! Hash::check($codes['email_code'], $challenge->email_code_hash))) {
            return false;
        }

        if (in_array('phone', $challenge->channels, true)
            && (! isset($codes['phone_code']) || $challenge->phone_code_hash === null
                || ! Hash::check($codes['phone_code'], $challenge->phone_code_hash))) {
            return false;
        }

        return true;
    }

    private function verificationError(string $status): JsonResponse
    {
        return match ($status) {
            'expired' => response()->json(['message' => 'انتهت صلاحية رموز التحقق. أعد إرسالها.'], 410),
            'locked' => response()->json(['message' => 'تم إيقاف المحاولات. أعد إرسال رموز جديدة.'], 429),
            'blocked' => response()->json(['message' => 'الحساب غير نشط. تواصل مع الدعم.'], 403),
            default => response()->json(['message' => 'رمز التحقق غير صحيح.'], 422),
        };
    }

    private function normalizePhone(string $phoneNumber): string
    {
        return preg_replace('/[\s().-]/', '', trim($phoneNumber)) ?? '';
    }
}
