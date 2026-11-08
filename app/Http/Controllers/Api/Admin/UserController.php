<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    /**
     * List all users with filtering, searching, and eager counts (No N+1 query).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->withCount('files'); // حماية من استعلامات N+1

        // بحث بالاسم أو البريد الإلكتروني مع حماية النطاق
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        // فلترة بالـ Role (admin / student / teacher)
        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->toString());
        }

        // فلترة بإمكانية المساهمة
        if ($request->has('can_contribute')) {
            $query->where('can_contribute', $request->boolean('can_contribute'));
        }

        // فلترة بالتخصص
        if ($request->filled('specialization')) {
            $query->where('specialization', $request->string('specialization')->toString());
        }

        $users = $query->orderByDesc('created_at')
            ->paginate($this->pageSize($request));

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Update user roles, contribution rights, or active status.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['sometimes', 'string', Rule::in(['student', 'teacher', 'admin'])],
            'can_contribute' => ['sometimes', 'boolean'],
            'specialization' => ['nullable', 'string', 'max:255'],
        ]);

        // حماية: منع الأدمن من سحب صلاحيات الأدمن عن نفسه
        if ($user->id === $request->user()->id && isset($validated['role']) && $validated['role'] !== 'admin') {
            return response()->json(['message' => 'You cannot remove admin role from yourself.'], 422);
        }

        try {
            DB::transaction(function () use ($user, $validated) {
                $user->forceFill($validated)->save();
            });

            return response()->json([
                'message' => 'User privileges updated successfully.',
                'data' => $user->fresh(),
            ]);
        } catch (Throwable $e) {
            Log::error("UserController: Failed to update user ID {$user->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to update user privileges.',
            ], 500);
        }
    }

    /**
     * Quick action to allow or block contribution rights.
     */
    public function setContribution(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'can_contribute' => ['required', 'boolean'],
        ]);

        try {
            DB::transaction(function () use ($user, $validated) {
                $user->forceFill(['can_contribute' => $validated['can_contribute']])->save();
            });

            return response()->json([
                'message' => $validated['can_contribute'] ? 'Contribution allowed.' : 'Contribution blocked.',
                'can_contribute' => $user->can_contribute,
            ]);
        } catch (Throwable $e) {
            Log::error("UserController: Failed to update contribution for user ID {$user->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to update contribution status.',
            ], 500);
        }
    }

    /**
     * Permanently delete a user account and revoke active tokens.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // حماية: منع الأدمن من حذف نفسه
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own admin account.'], 422);
        }

        // حماية: منع حذف الأدمن بواسطة أدمن آخر
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Action forbidden: Cannot delete another admin.'], 403);
        }

        try {
            DB::transaction(function () use ($user) {
                // إلغاء كافّة الـ Sanctum / Passport Access Tokens الخاصة به فوراً
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }

                $user->delete();
            });

            return response()->json(['message' => 'User account deleted successfully.']);
        } catch (Throwable $e) {
            Log::error("UserController: Failed to delete user account ID {$user->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to delete user account.',
            ], 500);
        }
    }
}

