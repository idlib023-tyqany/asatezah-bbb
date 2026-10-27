<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CatalogController extends Controller
{
    private const CATEGORIES_CACHE_KEY = 'catalog_categories_list';

    private const FILTERS_ALL_CACHE_KEY = 'catalog_filters_all';

    /**
     * Provide the categories (التخصص/الصف) and materials (المادة) used by the filters.
     * With optional caching and filtering by category_id.
     */
    public function filters(Request $request): JsonResponse
    {
        try {
            $categoryId = $request->filled('category_id') ? $request->integer('category_id') : null;

            // إذا تم اختيار تصنيف معين، نربط المواد بالتصنيف المختار
            if ($categoryId) {
                $materials = Material::where('category_id', $categoryId)
                    ->orderBy('name')
                    ->get(['id', 'category_id', 'name', 'slug']);

                return response()->json([
                    'success' => true,
                    'categories' => $this->getCachedCategories(),
                    'materials' => $materials,
                ]);
            }

            // في حال جلب كافة البيانات كاش سريع لمدة 24 ساعة
            $data = Cache::remember(self::FILTERS_ALL_CACHE_KEY, now()->addHours(24), function () {
                return [
                    'categories' => $this->getCachedCategories(),
                    'materials' => Material::orderBy('name')->get(['id', 'category_id', 'name', 'slug']),
                ];
            });

            return response()->json([
                'success' => true,
                ...$data,
            ]);
        } catch (Throwable $e) {
            Log::error("CatalogController: Failed to fetch filters. Error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to load filters data.',
            ], 500);
        }
    }

    /**
     * Clear filters cache when updated from Admin dashboard.
     */
    public static function clearFilterCache(): void
    {
        Cache::forget(self::CATEGORIES_CACHE_KEY);
        Cache::forget(self::FILTERS_ALL_CACHE_KEY);
    }

    /**
     * Get cached categories list.
     */
    private function getCachedCategories()
    {
        return Cache::remember(self::CATEGORIES_CACHE_KEY, now()->addHours(24), function () {
            return Category::query()->orderBy('name')->get(['id', 'name', 'slug', 'icon']);
        });
    }
}
