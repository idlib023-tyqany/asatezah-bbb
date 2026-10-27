<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the comment resource into an array for API/Flutter consumption.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        return [
            'id' => $this->id,
            'body' => $this->body,

            // جلب صاحب التعليق فقط عند تحميل العلاقة مسبقاً لمنع N+1 Query
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'avatar_url' => $this->user->avatar_path
                        ? asset('storage/' . $this->user->avatar_path)
                        : null,
                ];
            }),

            // تحديد ما إذا كان التعليق يخص المستخدم الحالي (لتسهيل إظهار زر التعديل/الحذف في Flutter)
            'is_mine' => $currentUser ? $this->user_id === $currentUser->id : false,

            // التواريخ وتنسيقها
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
