<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NotificationDeliveryService
{
    /**
     * Create a notification and deliver it to all non-admin users.
     */
    public function toAll(string $title, string $body, array $data = []): Notification
    {
        return DB::transaction(function () use ($title, $body, $data) {
            $notification = Notification::create([
                'audience' => Notification::AUDIENCE_ALL,
                'title' => strip_tags($title),
                'body' => strip_tags($body),
                'data' => $data,
            ]);

            $query = User::query()->where('role', '!=', User::ROLE_ADMIN);

            $this->attachToUsers($notification->id, $query);

            return $notification;
        });
    }

    /**
     * Create a notification and deliver it to users matching a specialization.
     */
    public function toSpecialization(string $specialization, string $title, string $body, array $data = []): Notification
    {
        return DB::transaction(function () use ($specialization, $title, $body, $data) {
            $notification = Notification::create([
                'audience' => Notification::AUDIENCE_SPECIALIZATION,
                'specialization' => $specialization,
                'title' => strip_tags($title),
                'body' => strip_tags($body),
                'data' => $data,
            ]);

            $query = User::query()
                ->where('role', '!=', User::ROLE_ADMIN)
                ->where('specialization', $specialization);

            $this->attachToUsers($notification->id, $query);

            return $notification;
        });
    }

    /**
     * Create a notification and deliver it to a specific single user.
     */
    public function toUser(int $userId, string $title, string $body, array $data = []): Notification
    {
        return DB::transaction(function () use ($userId, $title, $body, $data) {
            $notification = Notification::create([
                'audience' => Notification::AUDIENCE_ROLE,
                'title' => strip_tags($title),
                'body' => strip_tags($body),
                'data' => $data,
            ]);

            UserNotification::create([
                'user_id' => $userId,
                'notification_id' => $notification->id,
                'read' => false,
            ]);

            return $notification;
        });
    }

    /**
     * Helper method to attach notification records in chunks safely.
     */
    private function attachToUsers(int $notificationId, Builder $query): void
    {
        $now = now();

        $query->select('id')->chunkById(500, function ($users) use ($notificationId, $now) {
            $rows = $users->map(fn($user) => [
                'user_id' => $user->id,
                'notification_id' => $notificationId,
                'read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            UserNotification::insert($rows);
        });
    }
}
