<?php

namespace App\Http\Resources;

use App\Support\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the message resource into an array for Flutter consumption.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'body' => $this->body,
            'image_path' => $this->image_path,

            // توليد URL الصورة المرفقة باستخدام asset() لضمان الاستقرار في تطبيق Flutter
            'image_url' => FileStorage::url($this->image_path),

            // تحديد ما إذا كانت الرسالة ملك للمستخدم الحالي
            'is_mine' => $currentUser ? $this->sender_id === $currentUser->id : false,
            'mine' => $currentUser ? $this->sender_id === $currentUser->id : false,

            // بيانات المرسل بدون تسبب في استعلامات N+1
            'sender' => $this->whenLoaded('sender', function () {
                $isAdmin = method_exists($this->sender, 'isAdmin')
                    ? $this->sender->isAdmin()
                    : ($this->sender->role === 'admin');

                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                    'is_admin' => $isAdmin,
                    'avatar_url' => FileStorage::url($this->sender->avatar_path),
                ];
            }),

            // التواريخ وتنسيقها الموحد
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
