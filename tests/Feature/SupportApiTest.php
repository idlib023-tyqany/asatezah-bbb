<?php

namespace Tests\Feature;

use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_a_conversation_without_an_initial_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/support', ['subject' => 'Help with a resource'])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Help with a resource')
            ->assertJsonPath('data.status', SupportConversation::STATUS_OPEN)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('support_conversations', [
            'user_id' => $user->id,
            'subject' => 'Help with a resource',
        ]);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_user_can_send_a_message_and_close_their_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = SupportConversation::create([
            'user_id' => $user->id,
            'subject' => 'Help with a resource',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/support/{$conversation->id}/messages", ['body' => 'Please help.'])
            ->assertCreated()
            ->assertJsonPath('conversation_id', $conversation->id)
            ->assertJsonPath('mine', true);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Please help.',
        ]);

        $this->postJson("/api/support/{$conversation->id}/close")
            ->assertOk()
            ->assertJsonPath('status', SupportConversation::STATUS_CLOSED);

        $this->assertDatabaseHas('support_conversations', [
            'id' => $conversation->id,
            'status' => SupportConversation::STATUS_CLOSED,
        ]);
    }

    public function test_user_cannot_access_another_users_conversation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = SupportConversation::create([
            'user_id' => $otherUser->id,
            'subject' => 'Private conversation',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/support/{$conversation->id}/messages")
            ->assertForbidden();
    }
}
