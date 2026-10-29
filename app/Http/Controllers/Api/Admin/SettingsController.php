<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\AppInfoController;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SettingsController extends Controller
{
    private const CACHE_KEY = 'app_system_settings';

    /**
     * قائمة مفاتيح الإعدادات المسموح بها في النظام
     */
    private const ALLOWED_KEYS = [
        'app_name',
        'app_version',
        'logo_path',
        'link_telegram',
        'link_youtube',
        'link_facebook',
        'link_instagram',
        'maintenance_mode',
    ];

    /**
     * Get all app settings in a single database query with Cache support.
     */
    public function show(): JsonResponse
    {
        $settings = Cache::remember(self::CACHE_KEY, 86400, function () {
            $dbSettings = Setting::whereIn('key', self::ALLOWED_KEYS)
                ->pluck('value', 'key')
                ->toArray();

            $data = [];
            foreach (self::ALLOWED_KEYS as $key) {
                $data[$key] = $dbSettings[$key] ?? null;
            }

            return $data;
        });

        // تحويل مسار اللوجو إلى URL كامل باستخدام asset() لضمان عمله بسلاسة تامة
        if (! empty($settings['logo_path'])) {
            $settings['logo_url'] = FileStorage::url($settings['logo_path']);
        } else {
            $settings['logo_url'] = null;
        }

        return response()->json([
            'status' => 'success',
            'data' => $settings,
        ]);
    }

    /**
     * Update settings safely (key-value pairs).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'link_telegram' => ['nullable', 'url', 'max:500'],
            'link_youtube' => ['nullable', 'url', 'max:500'],
            'link_facebook' => ['nullable', 'url', 'max:500'],
            'link_instagram' => ['nullable', 'url', 'max:500'],
            'maintenance_mode' => ['nullable', 'boolean'],
        ]);

        try {
            DB::transaction(function () use ($request, $validated) {
                foreach ($validated as $key => $value) {
                    if ($request->has($key)) {
                        Setting::set($key, $value);
                    }
                }
            });

            // إفراغ الـ Cache ليتم تحديث البيانات فوراً
            Cache::forget(self::CACHE_KEY);
            if (class_exists(AppInfoController::class) && defined(AppInfoController::class.'::CACHE_KEY')) {
                Cache::forget(AppInfoController::CACHE_KEY);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Settings updated successfully.',
            ]);
        } catch (Throwable $e) {
            Log::error("SettingsController: Failed to update settings. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to update settings.',
            ], 500);
        }
    }

    /**
     * Upload the logo image safely and remove the old file.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        try {
            $path = null;

            DB::transaction(function () use ($request, &$path) {
                // جلب الشعار القديم وحذفه لتوفير المساحة
                $oldLogo = Setting::get('logo_path');
                if (! empty($oldLogo) && FileStorage::disk()->exists($oldLogo)) {
                    FileStorage::disk()->delete($oldLogo);
                }

                // تخزين الشعار الجديد
                $path = FileStorage::store($request->file('logo'), 'logos');
                Setting::set('logo_path', $path);
            });

            // إفراغ الـ Cache
            Cache::forget(self::CACHE_KEY);
            if (class_exists(AppInfoController::class) && defined(AppInfoController::class.'::CACHE_KEY')) {
                Cache::forget(AppInfoController::CACHE_KEY);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Logo uploaded successfully.',
                'data' => [
                    'logo_path' => $path,
                    'logo_url' => FileStorage::url($path),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error("SettingsController: Failed to upload logo. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to upload logo.',
            ], 500);
        }
    }
}
