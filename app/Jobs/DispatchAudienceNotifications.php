<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchAudienceNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 2;

    public function __construct(
        public int $notificationId,
        public string $audience,
        public ?string $specialization = null
    ) {}

    public function handle(): void
    {
        $notification = Notification::find($this->notificationId);
        if (! $notification) {
            Log::warning("DispatchAudienceNotifications: Notification ID {$this->notificationId} not found.");
            return;
        }

        $query = User::query()->where('role', '!=', User::ROLE_ADMIN);

        if ($this->audience === Notification::AUDIENCE_SPECIALIZATION && $this->specialization) {
            $query->where('specialization', $this->specialization);
        }

        $now = now();
        $notificationId = $this->notificationId;

        $query->select('id')->chunkById(500, function ($users) use ($notificationId, $now) {
            $rows = $users->map(fn ($user) => [
                'user_id' => $user->id,
                'notification_id' => $notificationId,
                'read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            UserNotification::insert($rows);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error("DispatchAudienceNotifications failed for notification ID {$this->notificationId}. Error: {$exception?->getMessage()}");
    }
}
