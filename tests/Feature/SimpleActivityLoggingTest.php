<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_activity_logging_works()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();

        // Count activities before
        $beforeCount = Activity::count();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'encrypted_content' => json_encode(['data' => 'test', 'iv' => 'test']),
            'content_hash' => 'test-hash',
            'type' => 'text',
            'status' => 'sent',
        ]);

        // Count activities after
        $afterCount = Activity::count();

        $this->assertEquals($beforeCount + 1, $afterCount, 'Message creation should create one activity log entry');

        $activity = Activity::where('subject_id', $message->id)
            ->where('subject_type', Message::class)
            ->where('log_name', 'chat')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('created', $activity->event);
    }

    public function test_message_updates_are_logged()
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'encrypted_content' => json_encode(['data' => 'test', 'iv' => 'test']),
            'content_hash' => 'test-hash',
            'type' => 'text',
            'status' => 'sent',
        ]);

        // Clear existing activities to focus on updates
        Activity::where('subject_id', $message->id)->where('event', 'created')->delete();

        // Update the message
        $message->update(['status' => 'read']);

        $updateActivity = Activity::where('subject_id', $message->id)
            ->where('subject_type', Message::class)
            ->where('log_name', 'chat')
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($updateActivity);
        $this->assertEquals('updated', $updateActivity->event);
    }

    public function test_activity_log_service_works()
    {
        $user = User::factory()->create();

        $beforeCount = Activity::count();

        // Use the ActivityLogService directly
        \App\Services\ActivityLogService::logAuth('test_event', 'Test authentication event', [
            'test_property' => 'test_value',
        ], $user);

        $afterCount = Activity::count();

        $this->assertEquals($beforeCount + 1, $afterCount);

        $activity = Activity::where('log_name', 'auth')
            ->where('event', 'test_event')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('test_event', $activity->event);
        $this->assertStringContainsString('Test authentication event', $activity->description);
        $this->assertArrayHasKey('test_property', $activity->properties->toArray());
    }
}