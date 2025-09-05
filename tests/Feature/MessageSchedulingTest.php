<?php

namespace Tests\Feature;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Organization;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Services\MessageSchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Carbon\Carbon;

class MessageSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create();
        $this->conversation = Conversation::factory()->create([
            'organization_id' => $this->organization->id
        ]);

        // Add user to conversation
        $this->conversation->participants()->create([
            'user_id' => $this->user->id,
            'role' => 'member'
        ]);
    }

    public function test_can_schedule_message()
    {
        $scheduledTime = now()->addHours(2);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/messages/schedule', [
                'conversation_id' => $this->conversation->id,
                'content' => 'This is a scheduled message',
                'scheduled_for' => $scheduledTime->toISOString(),
                'timezone' => 'America/New_York'
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'scheduled_message' => [
                'id',
                'conversation_id',
                'sender_id',
                'content',
                'scheduled_for',
                'timezone',
                'status'
            ],
            'message'
        ]);

        $this->assertDatabaseHas('scheduled_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => 'This is a scheduled message',
            'status' => 'scheduled',
            'timezone' => 'America/New_York'
        ]);
    }

    public function test_can_get_scheduled_messages_for_conversation()
    {
        $scheduledMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'status' => 'scheduled'
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->getJson("/api/v1/conversations/{$this->conversation->id}/scheduled-messages");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'scheduled_messages' => [
                '*' => [
                    'id',
                    'content',
                    'scheduled_for',
                    'status',
                    'sender'
                ]
            ],
            'pagination'
        ]);

        $messages = $response->json('scheduled_messages');
        $this->assertCount(1, $messages);
        $this->assertEquals($scheduledMessage->id, $messages[0]['id']);
    }

    public function test_message_scheduling_service_processes_ready_messages()
    {
        Queue::fake();

        $readyMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinutes(5),
            'content' => 'Ready to send message'
        ]);

        $futureMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'status' => 'scheduled',
            'scheduled_for' => now()->addHours(1),
            'content' => 'Future message'
        ]);

        $schedulingService = app(MessageSchedulingService::class);
        $processedCount = $schedulingService->processReadyMessages();

        $this->assertEquals(1, $processedCount);

        $readyMessage->refresh();
        $futureMessage->refresh();

        $this->assertEquals('sending', $readyMessage->status);
        $this->assertEquals('scheduled', $futureMessage->status);
    }

    public function test_can_cancel_scheduled_message()
    {
        $scheduledMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'status' => 'scheduled'
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->deleteJson("/api/v1/scheduled-messages/{$scheduledMessage->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Scheduled message cancelled successfully']);

        $scheduledMessage->refresh();
        $this->assertEquals('cancelled', $scheduledMessage->status);
        $this->assertNotNull($scheduledMessage->cancelled_at);
    }

    public function test_can_reschedule_message()
    {
        $scheduledMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'status' => 'scheduled',
            'scheduled_for' => now()->addHours(2)
        ]);

        $newScheduledTime = now()->addHours(4);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->putJson("/api/v1/scheduled-messages/{$scheduledMessage->id}", [
                'scheduled_for' => $newScheduledTime->toISOString(),
                'timezone' => 'UTC'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'scheduled_message' => [
                'id',
                'scheduled_for',
                'timezone',
                'status'
            ]
        ]);

        $scheduledMessage->refresh();
        $this->assertEquals('scheduled', $scheduledMessage->status);
        $this->assertEquals($newScheduledTime->format('Y-m-d H:i:s'), $scheduledMessage->scheduled_for->format('Y-m-d H:i:s'));
    }

    public function test_can_retry_failed_message()
    {
        $failedMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'status' => 'failed',
            'retry_count' => 1,
            'max_retries' => 3,
            'error_message' => 'Network timeout'
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson("/api/v1/scheduled-messages/{$failedMessage->id}/retry");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Message queued for retry']);

        $failedMessage->refresh();
        $this->assertEquals('scheduled', $failedMessage->status);
        $this->assertNull($failedMessage->error_message);
    }

    public function test_cannot_retry_message_with_max_retries_reached()
    {
        $failedMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'status' => 'failed',
            'retry_count' => 3,
            'max_retries' => 3,
            'error_message' => 'Maximum retries reached'
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson("/api/v1/scheduled-messages/{$failedMessage->id}/retry");

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Cannot retry this scheduled message']);
    }

    public function test_bulk_schedule_messages()
    {
        $messages = [
            [
                'content' => 'First scheduled message',
                'scheduled_for' => now()->addHours(1)->toISOString(),
            ],
            [
                'content' => 'Second scheduled message',
                'scheduled_for' => now()->addHours(2)->toISOString(),
            ],
            [
                'content' => 'Third scheduled message',
                'scheduled_for' => now()->addHours(3)->toISOString(),
            ]
        ];

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/messages/bulk-schedule', [
                'conversation_id' => $this->conversation->id,
                'messages' => $messages,
                'timezone' => 'UTC'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'results',
            'summary' => [
                'total',
                'successful',
                'failed'
            ]
        ]);

        $this->assertDatabaseCount('scheduled_messages', 3);

        $summary = $response->json('summary');
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(3, $summary['successful']);
        $this->assertEquals(0, $summary['failed']);
    }

    public function test_scheduled_message_timezone_handling()
    {
        $scheduledMessage = ScheduledMessage::factory()->create([
            'scheduled_for' => Carbon::parse('2024-01-01 12:00:00 UTC'),
            'timezone' => 'America/New_York'
        ]);

        $timeInTimezone = $scheduledMessage->getScheduledForInTimezone();
        $this->assertEquals('America/New_York', $timeInTimezone->getTimezone()->getName());
    }

    public function test_can_get_scheduling_statistics()
    {
        ScheduledMessage::factory()->count(5)->create([
            'conversation_id' => $this->conversation->id,
            'status' => 'scheduled'
        ]);

        ScheduledMessage::factory()->count(3)->create([
            'conversation_id' => $this->conversation->id,
            'status' => 'sent'
        ]);

        ScheduledMessage::factory()->count(2)->create([
            'conversation_id' => $this->conversation->id,
            'status' => 'failed'
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->getJson('/api/v1/messages/scheduling-statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'statistics' => [
                'total_scheduled',
                'pending',
                'sent',
                'failed',
                'cancelled',
                'success_rate',
                'average_delivery_time',
                'upcoming_24h',
                'overdue'
            ]
        ]);

        $stats = $response->json('statistics');
        $this->assertEquals(10, $stats['total_scheduled']);
        $this->assertEquals(5, $stats['pending']);
        $this->assertEquals(3, $stats['sent']);
        $this->assertEquals(2, $stats['failed']);
    }

    public function test_recurring_message_scheduling()
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/messages/schedule-recurring', [
                'conversation_id' => $this->conversation->id,
                'content' => 'Daily standup reminder',
                'start_time' => now()->addDay()->format('H:i:s'),
                'start_date' => now()->addDay()->format('Y-m-d'),
                'recurrence_pattern' => 'daily',
                'end_date' => now()->addWeeks(2)->format('Y-m-d'),
                'timezone' => 'UTC'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'scheduled_messages',
            'summary' => [
                'total_created',
                'pattern',
                'start_date',
                'end_date'
            ]
        ]);

        // Should create 14 messages (2 weeks of daily messages)
        $this->assertDatabaseCount('scheduled_messages', 14);

        // Verify all messages have recurring metadata
        $messages = ScheduledMessage::where('conversation_id', $this->conversation->id)->get();
        foreach ($messages as $message) {
            $this->assertTrue($message->getMetadata('recurring'));
            $this->assertEquals('daily', $message->getMetadata('recurrence_pattern'));
        }
    }

    public function test_message_delivery_simulation()
    {
        $scheduledMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'status' => 'sending',
            'content' => 'Test delivery message'
        ]);

        $schedulingService = app(MessageSchedulingService::class);
        $sentMessage = $schedulingService->sendScheduledMessage($scheduledMessage);

        $this->assertNotNull($sentMessage);
        $this->assertInstanceOf(Message::class, $sentMessage);

        $scheduledMessage->refresh();
        $this->assertEquals('sent', $scheduledMessage->status);
        $this->assertEquals($sentMessage->id, $scheduledMessage->sent_message_id);
        $this->assertNotNull($scheduledMessage->sent_at);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => 'Test delivery message'
        ]);
    }

    public function test_overdue_message_detection()
    {
        $overdueMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'status' => 'sending',
            'updated_at' => now()->subHours(2)
        ]);

        $normalMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'status' => 'sending',
            'updated_at' => now()->subMinutes(5)
        ]);

        $overdueMessages = ScheduledMessage::overdue(60)->get();

        $this->assertCount(1, $overdueMessages);
        $this->assertEquals($overdueMessage->id, $overdueMessages->first()->id);
    }

    public function test_unauthorized_access_denied()
    {
        $otherUser = User::factory()->create();
        $otherConversation = Conversation::factory()->create();

        $scheduledMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $otherConversation->id,
            'sender_id' => $otherUser->id
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->deleteJson("/api/v1/scheduled-messages/{$scheduledMessage->id}");

        $response->assertStatus(404); // Message not found in accessible conversations
    }

    public function test_invalid_timezone_handling()
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/messages/schedule', [
                'conversation_id' => $this->conversation->id,
                'content' => 'Test message',
                'scheduled_for' => now()->addHours(1)->toISOString(),
                'timezone' => 'Invalid/Timezone'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['timezone']);
    }

    public function test_past_scheduling_time_validation()
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/messages/schedule', [
                'conversation_id' => $this->conversation->id,
                'content' => 'Test message',
                'scheduled_for' => now()->subHours(1)->toISOString(),
                'timezone' => 'UTC'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['scheduled_for']);
    }
}