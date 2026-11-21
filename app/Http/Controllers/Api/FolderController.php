<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FolderController extends Controller
{
    public function index(): JsonResponse
    {
        $root = Folder::query()->where('is_root', true)->first();
        $folders = $root
            ? $this->childrenOf($root->id)
            : Folder::query()->whereNull('parent_id')->orderBy('order_index')->orderBy('name')->get();

        return response()->json([
            'data' => $folders->map(fn (Folder $folder): array => $this->folderSummary($folder)),
        ]);
    }

    public function getRoot(): JsonResponse
    {
        $root = Folder::query()->where('is_root', true)->first();
        if (! $root) {
            return $this->index();
        }

        return $this->getFolderDetails((string) $root->id);
    }

    public function getFolderDetails(string $id): JsonResponse
    {
        $folder = Folder::query()->findOrFail($id);
        $children = $this->childrenOf($folder->id);
        $files = File::query()
            ->where('folder_id', $folder->id)
            ->published()
            ->with(['category:id,name,slug', 'material:id,name,slug', 'user:id,name,avatar,role'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'parent_id' => $folder->parent_id,
                'breadcrumbs' => $folder->getBreadcrumbs(),
                'children' => $children->map(fn (Folder $child): array => $this->folderSummary($child)),
                'files' => FileResource::collection($files),
            ],
        ]);
    }

    private function childrenOf(int $folderId): Collection
    {
        $hierarchyChildIds = DB::table('folder_hierarchy')
            ->where('parent_folder_id', $folderId)
            ->pluck('child_folder_id');

        return Folder::query()
            ->where(function ($query) use ($folderId, $hierarchyChildIds): void {
                $query->where('parent_id', $folderId);
                if ($hierarchyChildIds->isNotEmpty()) {
                    $query->orWhereIn('id', $hierarchyChildIds);
                }
            })
            ->orderBy('order_index')
            ->orderBy('name')
            ->get();
    }

    private function folderSummary(Folder $folder): array
    {
        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parent_id,
            'files_count' => File::query()
                ->where('folder_id', $folder->id)
                ->published()
                ->count(),
        ];
    }

    public function update(Request $request, Folder $folder): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:folders,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
        ]);

        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            $parentId = (int) $validated['parent_id'];
            $descendantIds = $this->descendantIds($folder);
            if ($parentId === $folder->id || in_array($parentId, $descendantIds, true)) {
                return response()->json([
                    'message' => 'The selected parent would create a folder cycle.',
                    'errors' => ['parent_id' => ['The selected parent would create a folder cycle.']],
                ], 422);
            }
        }

        $folder->update($validated);

        return response()->json(['data' => [
            'id' => $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parent_id,
        ]]);
    }

    public function destroy(Folder $folder): JsonResponse
    {
        $folder->delete();

        return response()->json(['message' => 'Folder deleted successfully.']);
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(Folder $folder): array
    {
        $descendants = [];
        $pending = [$folder->id];

        while ($pending !== []) {
            $parentId = array_pop($pending);
            $children = $this->childrenOf($parentId);
            foreach ($children as $child) {
                if (! in_array($child->id, $descendants, true)) {
                    $descendants[] = $child->id;
                    $pending[] = $child->id;
                }
            }
        }

        return $descendants;
    }
}
