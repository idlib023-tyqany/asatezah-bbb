<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FolderController extends Controller
{
    /**
     * List folders with the number of published files in each folder.
     */
    public function index(): JsonResponse
    {
        $folders = Folder::query()
            ->withCount(['files as files_count' => fn ($query) => $query->published()])
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'category_id']);

        return response()->json(['data' => $folders]);
    }

    /**
     * Display a folder, its direct child folders, and its published files.
     */
    public function show(Request $request, Folder $folder): JsonResponse
    {
        $userId = $request->user('sanctum')?->id ?? $request->user()?->id;
        $relations = [
            'category:id,name,slug',
            'material:id,name,slug',
            'user:id,name,avatar,role',
        ];

        if ($userId !== null) {
            $relations['favorites'] = fn ($query) => $query->where('user_id', $userId);
            $relations['ratings'] = fn ($query) => $query->where('user_id', $userId);
        }

        $children = $folder->children()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'category_id']);
        $files = $folder->files()
            ->published()
            ->with($relations)
            ->latest()
            ->get();

        return response()->json([
            'data' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'parent_id' => $folder->parent_id,
                'category_id' => $folder->category_id,
                'breadcrumbs' => $folder->getBreadcrumbs(),
                'children' => $children,
                'files' => FileResource::collection($files)->resolve($request),
            ],
        ]);
    }

    /**
     * Rename a folder or move it to a different parent.
     */
    public function update(Request $request, Folder $folder): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:folders,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($folder): void {
                    if ($value === null) {
                        return;
                    }

                    $visited = [$folder->id => true];
                    $frontier = [$folder->id];

                    while ($frontier !== []) {
                        $children = Folder::query()
                            ->whereIn('parent_id', $frontier)
                            ->pluck('id')
                            ->all();

                        $frontier = [];
                        foreach ($children as $childId) {
                            if (! isset($visited[$childId])) {
                                $visited[$childId] = true;
                                $frontier[] = $childId;
                            }
                        }
                    }

                    if (isset($visited[(int) $value])) {
                        $fail('A folder cannot be moved into itself or one of its descendants.');
                    }
                },
            ],
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'folder' => ['Provide a name or parent_id to update the folder.'],
            ]);
        }

        $folder->update($validated);

        return response()->json([
            'message' => 'Folder updated successfully.',
            'data' => $folder->fresh(),
        ]);
    }
}
