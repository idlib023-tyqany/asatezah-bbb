<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_the_authenticated_users_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::create([
            'audience' => Notification::AUDIENCE_ALL,
            'title' => 'Library update',
            'body' => 'New material is available.',
        ]);

        $userNotification = UserNotification::create([
            'user_id' => $user->id,
            'notification_id' => $notification->id,
        ]);

        UserNotification::create([
            'user_id' => $otherUser->id,
            'notification_id' => $notification->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $userNotification->id)
            ->assertJsonPath('data.0.title', 'Library update')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_caps_requested_page_size(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?per_page=1000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_user_can_mark_own_notification_as_read_and_mark_all_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'audience' => Notification::AUDIENCE_ALL,
            'title' => 'Library update',
            'body' => 'New material is available.',
        ]);
        $userNotification = UserNotification::create([
            'user_id' => $user->id,
            'notification_id' => $notification->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$userNotification->id}/read")
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'id' => $userNotification->id,
            'read' => true,
        ]);

        $this->postJson('/api/notifications/read-all')->assertOk();

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::create([
            'audience' => Notification::AUDIENCE_ALL,
            'title' => 'Private',
            'body' => 'Notification body.',
        ]);
        $userNotification = UserNotification::create([
            'user_id' => $otherUser->id,
            'notification_id' => $notification->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/notifications/{$userNotification->id}/read")
            ->assertNotFound();

        $this->assertDatabaseHas('user_notifications', [
            'id' => $userNotification->id,
            'read' => false,
        ]);
    }

    public function test_admin_broadcast_persists_extra_data_and_excludes_admin_users(): void
    {
        $admin = User::factory()->admin()->create();
        $recipient = User::factory()->teacher()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/notifications/all', [
                'title' => 'New material',
                'body' => 'A new file was added.',
                'data' => ['file_id' => 42],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'audience' => Notification::AUDIENCE_ALL,
            'title' => 'New material',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $recipient->id,
            'read' => false,
        ]);
        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertSame(['file_id' => 42], Notification::query()->sole()->data);
    }
}
