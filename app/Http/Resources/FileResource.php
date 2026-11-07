<?php

namespace App\Http\Resources;

use App\Support\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * Transform the file resource into an array for Flutter API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum') ?? $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'filename' => $this->filename,
            'file_type' => $this->file_type ?? $this->mime_type,
            'mime_type' => $this->mime_type ?? 'application/pdf',
            'file_size' => $this->file_size,
            'raw_file_size' => $this->file_size,
            'file_size_formatted' => $this->formatFileSize($this->file_size),
            'status' => $this->status ?? 'published',

            // URLs المباشرة لتطبيق Flutter باستخدام asset()
            'download_url' => route('api.files.download', ['file' => $this->id]),
            'cover_image_url' => FileStorage::url($this->thumbnail),

            // العلاقات الحماية ضد N+1 Query
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),

            'material' => $this->whenLoaded('material', function () {
                return [
                    'id' => $this->material->id,
                    'name' => $this->material->name,
                ];
            }),

            'publisher' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'role' => $this->user->role ?? 'teacher',
                    'avatar_url' => FileStorage::url($this->user->avatar_path),
                ];
            }),

            // العدادات والإحصائيات
            'stats' => [
                'views_count' => (int) $this->views_count,
                'downloads_count' => (int) $this->downloads_count,
                'favorites_count' => (int) $this->favorites_count,
                'avg_rating' => round((float) ($this->avg_rating ?? 0), 1),
                'ratings_count' => (int) ($this->ratings_count ?? 0),
            ],

            // حالة تفاعل المستخدم الحالي (تجنب N+1 Queries)
            'is_favorite' => $user ? $this->checkIsFavorite($user->id) : false,
            'my_rating' => $user ? $this->getUserRatingValue($user->id) : null,

            // التواريخ وتنسيقها
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }

    /**
     * التحقق مما إذا كان الملف في مفضلة المستخدم بأداء عالي
     */
    private function checkIsFavorite(int $userId): bool
    {
        if ($this->relationLoaded('favorites')) {
            return $this->favorites->contains('user_id', $userId);
        }

        return $this->favorites()->where('user_id', $userId)->exists();
    }

    /**
     * جلب قيمة تقييم المستخدم الحالي للكتيب
     */
    private function getUserRatingValue(int $userId): ?int
    {
        if ($this->relationLoaded('ratings')) {
            $rating = $this->ratings->firstWhere('user_id', $userId);

            return $rating ? (int) $rating->value : null;
        }

        $value = $this->ratings()->where('user_id', $userId)->value('value');

        return $value === null ? null : (int) $value;
    }

    /**
     * تحويل حجم الملف بالبايت إلى صيغة مقروءة (KB, MB)
     */
    private function formatFileSize(?int $bytes): string
    {
        if (! $bytes || $bytes <= 0) {
            return '0 KB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }
}
