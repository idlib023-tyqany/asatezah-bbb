<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\CatalogController as PublicCatalogController;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class CatalogController extends Controller
{
    /**
     * List categories with materials/files count, supporting search & pagination.
     */
    public function categories(Request $request): JsonResponse
    {
        $query = Category::withCount(['materials', 'files']);

        // ميزة البحث الديناميكي
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('name')->paginate($this->pageSize($request, 15));

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Store a new category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
        ]);

        try {
            $category = DB::transaction(function () use ($validated) {
                return Category::create([
                    'name' => $validated['name'],
                    'slug' => $this->generateUniqueSlug($validated['name'], Category::class),
                ]);
            });

            PublicCatalogController::clearFilterCache();

            return response()->json([
                'message' => 'Category created successfully.',
                'data' => $category,
            ], 201);
        } catch (Throwable $e) {
            Log::error("Admin CatalogController: Failed to create category. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to create category.',
            ], 500);
        }
    }

    /**
     * Update a category.
     */
    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
        ]);

        try {
            $category = DB::transaction(function () use ($category, $validated) {
                $category->update([
                    'name' => $validated['name'],
                    'slug' => $this->generateUniqueSlug($validated['name'], Category::class, $category->id),
                ]);

                return $category;
            });

            PublicCatalogController::clearFilterCache();

            return response()->json([
                'message' => 'Category updated successfully.',
                'data' => $category,
            ]);
        } catch (Throwable $e) {
            Log::error("Admin CatalogController: Failed to update category ID {$category->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to update category.',
            ], 500);
        }
    }

    /**
     * Delete a category.
     */
    public function destroyCategory(Category $category): JsonResponse
    {
        $deleted = DB::transaction(function () use ($category) {
            if ($category->files()->exists() || $category->materials()->exists()) {
                return false;
            }

            $category->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'message' => 'Cannot delete category because it contains linked materials or files.',
            ], 422);
        }

        PublicCatalogController::clearFilterCache();

        return response()->json(['message' => 'Category deleted successfully.']);
    }

    /**
     * List materials with filtering by category, search & pagination.
     */
    public function materials(Request $request): JsonResponse
    {
        $query = Material::withCount('files')->with('category:id,name');

        // ميزة التصفية حسب التصنيف
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // ميزة البحث بالاسم
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $materials = $query->orderBy('name')->paginate($this->pageSize($request, 15));

        return response()->json([
            'status' => 'success',
            'data' => $materials,
        ]);
    }

    /**
     * Store a new material.
     */
    public function storeMaterial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:materials,name'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        try {
            $material = DB::transaction(function () use ($validated) {
                return Material::create([
                    'name' => $validated['name'],
                    'slug' => $this->generateUniqueSlug($validated['name'], Material::class),
                    'category_id' => $validated['category_id'] ?? null,
                ]);
            });

            PublicCatalogController::clearFilterCache();

            return response()->json([
                'message' => 'Material created successfully.',
                'data' => $material,
            ], 201);
        } catch (Throwable $e) {
            Log::error("Admin CatalogController: Failed to create material. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to create material.',
            ], 500);
        }
    }

    /**
     * Update a material.
     */
    public function updateMaterial(Request $request, Material $material): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('materials', 'name')->ignore($material->id)],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
        ]);

        try {
            $material = DB::transaction(function () use ($material, $validated) {
                $material->update([
                    'name' => $validated['name'],
                    'slug' => $this->generateUniqueSlug($validated['name'], Material::class, $material->id),
                    'category_id' => array_key_exists('category_id', $validated) ? $validated['category_id'] : $material->category_id,
                ]);

                return $material;
            });

            // تم إزالة الكلمة الخاطئة PublicCount من هنا
            PublicCatalogController::clearFilterCache();

            return response()->json([
                'message' => 'Material updated successfully.',
                'data' => $material,
            ]);
        } catch (Throwable $e) {
            Log::error("Admin CatalogController: Failed to update material ID {$material->id}. Error: {$e->getMessage()}");

            return response()->json([
                'message' => 'Failed to update material.',
            ], 500);
        }
    }

    /**
     * Delete a material.
     */
    public function destroyMaterial(Material $material): JsonResponse
    {
        $deleted = DB::transaction(function () use ($material) {
            if ($material->files()->exists()) {
                return false;
            }

            $material->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'message' => 'Cannot delete material because it contains linked files.',
            ], 422);
        }

        PublicCatalogController::clearFilterCache();

        return response()->json(['message' => 'Material deleted successfully.']);
    }

    /**
     * دالة مساعدة لتوليد slug يدعم اللغة العربية بشكل آمن وبدون تكرار.
     */
    protected function generateUniqueSlug(string $title, string $modelClass, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title, '-', null);

        if (empty($slug)) {
            $slug = preg_replace('/[^\p{L}\p{N}\s]/u', '', trim($title));
            $slug = preg_replace('/\s+/u', '-', $slug);
        }

        $originalSlug = $slug;
        $count = 1;

        while ($modelClass::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }
}
