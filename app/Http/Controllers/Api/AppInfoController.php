<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\FileStorage;
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
        $appInfo = Cache::remember(self::CACHE_KEY, 86400, function () {
            $keys = [
                'app_name',
                'logo_path',
                'app_version',
                'min_supported_version',
                'link_telegram',
                'link_youtube',
                'link_facebook',
                'link_instagram',
                'link_website',
                'link_whatsapp',
                'link_privacy_policy',
                'maintenance_mode',
            ];

            $settings = Setting::whereIn('key', $keys)
                ->pluck('value', 'key')
                ->toArray();

            $logoPath = $settings['logo_path'] ?? null;
            $logoUrl = FileStorage::url($logoPath);

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
                    'whatsapp' => $settings['link_whatsapp'] ?? null,
                    'website' => $settings['link_website'] ?? null,
                    'privacy_policy' => $settings['link_privacy_policy'] ?? null,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $appInfo,
        ]);
    }
}
