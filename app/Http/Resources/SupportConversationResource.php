<?php

namespace App\Http\Resources;

use App\Support\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportConversationResource extends JsonResource
{
    /**
     * Transform the conversation resource into a safe API representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'status' => $this->status,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => FileStorage::url($this->user->avatar_path),
            ]),
            'admin' => $this->whenLoaded('admin', fn () => $this->admin
                ? [
                    'id' => $this->admin->id,
                    'name' => $this->admin->name,
                ]
                : null),
            'latest_message' => $this->whenLoaded(
                'latestMessage',
                fn () => $this->latestMessage
                    ? new MessageResource($this->latestMessage)
                    : null
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
