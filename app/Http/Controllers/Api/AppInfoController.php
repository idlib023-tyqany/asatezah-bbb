<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AppInfoController extends Controller
{
    public const CACHE_KEY = 'public_app_info_v1';

    /**
     * Provide application info, social links, logo URL, and version checks for Flutter client.
     */
    public function show(): JsonResponse
    {
        // استخدام التخزين المؤقت Cache لمنع الضغط على قاعدة البيانات
        $appInfo = Cache::remember(self::CACHE_KEY, 86400, function () {
            $keys = [
                'app_name',
                'logo_path',
                'app_version',
                'min_supported_version', // أدنى نسخة مدعومة لتفعيل Force Update
                'link_telegram',
                'link_youtube',
                'link_facebook',
                'link_instagram',
                'maintenance_mode',
            ];

            // جلب كافة المفاتيح المطلوبة باستعلام SQL واحد فقط
            $settings = Setting::whereIn('key', $keys)
                ->pluck('value', 'key')
                ->toArray();

            $logoPath = $settings['logo_path'] ?? null;

            // استخدام asset() لضمان توليد URL كامل ومستقر لتطبيق Flutter
            $logoUrl = ! empty($logoPath) ? asset('storage/' . $logoPath) : null;

            return [
                'name' => $settings['app_name'] ?? 'أساتذة',
                'logo_path' => $logoPath,
                'logo_url' => $logoUrl,
                'version' => $settings['app_version'] ?? '1.0.0',
                'min_supported_version' => $settings['min_supported_version'] ?? '1.0.0',
                'maintenance_mode' => filter_var($settings['maintenance_mode'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'links' => [
                    'telegram' => $settings['link_telegram'] ?? null,
                    'youtube' => $settings['link_youtube'] ?? null,
                    'facebook' => $settings['link_facebook'] ?? null,
                    'instagram' => $settings['link_instagram'] ?? null,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $appInfo,
        ]);
    }
}
