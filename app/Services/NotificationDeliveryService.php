<?php

namespace App\Services;

use App\Jobs\DispatchAudienceNotifications;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NotificationDeliveryService
{
    /**
     * Create a notification and dispatch background delivery to all non-admin users.
     */
    public function toAll(string $title, string $body, array $data = []): Notification
    {
        $notification = Notification::create([
            'audience' => Notification::AUDIENCE_ALL,
            'title' => strip_tags($title),
            'body' => strip_tags($body),
            'data' => $data,
        ]);

        dispatch(new DispatchAudienceNotifications($notification->id, Notification::AUDIENCE_ALL));

        return $notification;
    }

    /**
     * Create a notification and dispatch background delivery to users matching a specialization.
     */
    public function toSpecialization(string $specialization, string $title, string $body, array $data = []): Notification
    {
        $notification = Notification::create([
            'audience' => Notification::AUDIENCE_SPECIALIZATION,
            'specialization' => $specialization,
            'title' => strip_tags($title),
            'body' => strip_tags($body),
            'data' => $data,
        ]);

        dispatch(new DispatchAudienceNotifications(
            $notification->id,
            Notification::AUDIENCE_SPECIALIZATION,
            $specialization
        ));

        return $notification;
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
}
