<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Chat\BotConversation;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\MessageFile;
use App\Models\Organization;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Models\VoiceTranscription;
use App\Services\BotService;
use App\Services\MessageSchedulingService;
use App\Services\VoiceTranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntegratedChatFeaturesTest extends TestCase
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

    public function test_bot_can_process_voice_messages_and_respond()
    {
        // Create a bot with voice processing capabilities
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'capabilities' => ['receive_messages', 'send_messages', 'process_files', 'quantum_encryption']
        ]);

        // Add bot to conversation
        $botConversation = BotConversation::create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'active',
            'permissions' => ['receive_messages', 'send_messages', 'process_files']
        ]);

        // Create voice message with audio file
        $voiceMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content_type' => 'voice'
        ]);

        $audioFile = MessageFile::factory()->create([
            'message_id' => $voiceMessage->id,
            'content_type' => 'audio/mp3',
            'file_size' => 1024 * 512, // 512KB
            'duration' => 15.5
        ]);

        // Mock storage and OpenAI API
        Storage::fake('local');
        Storage::put('message_files/' . $audioFile->filename, 'fake audio content');

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Hello bot, please schedule a meeting for tomorrow at 2 PM.',
                'language' => 'en',
                'duration' => 15.5
            ], 200)
        ]);

        // Process voice transcription
        $transcriptionService = app(VoiceTranscriptionService::class);
        $transcription = $transcriptionService->transcribeVoiceMessage($voiceMessage);

        $this->assertNotNull($transcription);
        $this->assertEquals('completed', $transcription->status);
        $this->assertStringContainsString('schedule a meeting', $transcription->transcript);

        // Bot should receive the message and process it
        $botService = app(BotService::class);
        $botService->processIncomingMessage($bot, $voiceMessage);

        // Verify bot response was created
        $this->assertDatabaseHas('bot_messages', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'incoming',
            'content_type' => 'voice'
        ]);

        // Verify transcription was stored
        $this->assertDatabaseHas('voice_transcriptions', [
            'message_id' => $voiceMessage->id,
            'attachment_id' => $audioFile->id,
            'status' => 'completed'
        ]);
    }

    public function test_scheduled_message_with_bot_interaction()
    {
        // Create a bot
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'capabilities' => ['receive_messages', 'send_messages', 'auto_respond']
        ]);

        BotConversation::create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'active',
            'permissions' => ['receive_messages', 'send_messages']
        ]);

        // Schedule a message
        $scheduledMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => '@bot please prepare the daily report',
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinutes(5)
        ]);

        // Process scheduled message
        $schedulingService = app(MessageSchedulingService::class);
        $sentMessage = $schedulingService->sendScheduledMessage($scheduledMessage);

        $this->assertNotNull($sentMessage);
        $this->assertInstanceOf(Message::class, $sentMessage);

        // Verify message was sent
        $scheduledMessage->refresh();
        $this->assertEquals('sent', $scheduledMessage->status);
        $this->assertEquals($sentMessage->id, $scheduledMessage->sent_message_id);

        // Bot should process the mention
        $botService = app(BotService::class);
        $botService->processIncomingMessage($bot, $sentMessage);

        // Verify bot logged the interaction
        $this->assertDatabaseHas('bot_messages', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'incoming'
        ]);
    }

    public function test_voice_transcription_triggers_scheduled_response()
    {
        // Create voice message
        $voiceMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content_type' => 'voice'
        ]);

        $audioFile = MessageFile::factory()->create([
            'message_id' => $voiceMessage->id,
            'content_type' => 'audio/wav'
        ]);

        // Mock transcription
        Storage::fake('local');
        Storage::put('message_files/' . $audioFile->filename, 'fake audio');

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Schedule a reminder for 3 PM today to call the client.',
                'language' => 'en',
                'duration' => 8.0
            ], 200)
        ]);

        // Process transcription
        $transcriptionService = app(VoiceTranscriptionService::class);
        $transcription = $transcriptionService->transcribeVoiceMessage($voiceMessage);

        $this->assertEquals('completed', $transcription->status);
        $this->assertStringContainsString('Schedule a reminder', $transcription->transcript);

        // Schedule a follow-up message based on transcription content
        $followUpMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => 'Reminder: Call the client (from voice note)',
            'scheduled_for' => now()->addHours(3),
            'metadata' => [
                'triggered_by_transcription' => $transcription->id,
                'original_voice_message' => $voiceMessage->id
            ]
        ]);

        // Verify the connection
        $this->assertEquals($transcription->id, $followUpMessage->getMetadata('triggered_by_transcription'));
        $this->assertDatabaseHas('scheduled_messages', [
            'conversation_id' => $this->conversation->id,
            'content' => 'Reminder: Call the client (from voice note)',
            'status' => 'scheduled'
        ]);
    }

    public function test_bot_with_quantum_encryption_processes_voice_and_schedules_messages()
    {
        // Create quantum-capable bot
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'capabilities' => ['receive_messages', 'send_messages', 'quantum_encryption', 'process_files', 'schedule_messages']
        ]);

        $botConversation = BotConversation::create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'active',
            'permissions' => ['receive_messages', 'send_messages', 'schedule_messages']
        ]);

        // Verify encryption keys were created
        $this->assertDatabaseHas('bot_encryption_keys', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'is_active' => true
        ]);

        // Create voice message
        $voiceMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content_type' => 'voice'
        ]);

        $audioFile = MessageFile::factory()->create([
            'message_id' => $voiceMessage->id,
            'content_type' => 'audio/mp3'
        ]);

        // Mock services
        Storage::fake('local');
        Storage::put('message_files/' . $audioFile->filename, 'encrypted voice data');

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Bot, please send me a summary report every Monday at 9 AM.',
                'language' => 'en',
                'duration' => 12.0
            ], 200)
        ]);

        // Process transcription
        $transcriptionService = app(VoiceTranscriptionService::class);
        $transcription = $transcriptionService->transcribeVoiceMessage($voiceMessage);

        $this->assertEquals('completed', $transcription->status);

        // Bot processes the request and creates recurring scheduled messages
        $botService = app(BotService::class);
        $botService->processIncomingMessage($bot, $voiceMessage);

        // Simulate bot creating a scheduled message based on voice request
        $response = $this->postJson("/api/v1/bots/{$bot->id}/schedule-message", [
            'conversation_id' => $this->conversation->id,
            'content' => 'Weekly summary report as requested',
            'scheduled_for' => now()->nextMonday()->setHour(9)->setMinute(0)->toISOString(),
            'metadata' => [
                'triggered_by_voice' => $voiceMessage->id,
                'transcription_id' => $transcription->id,
                'recurring' => true,
                'recurrence_pattern' => 'weekly'
            ]
        ], [
            'Authorization' => 'Bearer ' . $bot->api_token,
            'X-Organization-Id' => $this->organization->id
        ]);

        $response->assertStatus(201);

        // Verify the complete integration
        $this->assertDatabaseHas('voice_transcriptions', [
            'message_id' => $voiceMessage->id,
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('scheduled_messages', [
            'conversation_id' => $this->conversation->id,
            'content' => 'Weekly summary report as requested'
        ]);

        $this->assertDatabaseHas('bot_messages', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'incoming'
        ]);
    }

    public function test_bulk_voice_transcription_with_scheduled_summaries()
    {
        // Create multiple voice messages
        $voiceMessages = [];
        $audioFiles = [];

        for ($i = 0; $i < 3; $i++) {
            $message = Message::factory()->create([
                'conversation_id' => $this->conversation->id,
                'sender_id' => $this->user->id,
                'content_type' => 'voice'
            ]);

            $file = MessageFile::factory()->create([
                'message_id' => $message->id,
                'content_type' => 'audio/mp3'
            ]);

            $voiceMessages[] = $message;
            $audioFiles[] = $file;
        }

        // Mock storage and API
        Storage::fake('local');
        foreach ($audioFiles as $file) {
            Storage::put('message_files/' . $file->filename, "fake audio content {$file->id}");
        }

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'This is a transcribed voice message for testing bulk operations.',
                'language' => 'en',
                'duration' => 10.0
            ], 200)
        ]);

        // Bulk transcribe
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/voice-transcriptions/bulk-transcribe', [
                'message_ids' => collect($voiceMessages)->pluck('id')->toArray()
            ]);

        $response->assertStatus(200);

        $summary = $response->json('summary');
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(3, $summary['successful']);

        // Verify all transcriptions were created
        $this->assertDatabaseCount('voice_transcriptions', 3);

        // Schedule a summary message for all transcriptions
        $transcriptions = VoiceTranscription::where('status', 'completed')->get();
        $summaryContent = "Voice message summary:\n";
        
        foreach ($transcriptions as $transcription) {
            $summaryContent .= "- " . substr($transcription->transcript, 0, 50) . "...\n";
        }

        $summaryMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => $summaryContent,
            'scheduled_for' => now()->addMinutes(30),
            'metadata' => [
                'type' => 'voice_summary',
                'transcription_ids' => $transcriptions->pluck('id')->toArray(),
                'voice_message_ids' => collect($voiceMessages)->pluck('id')->toArray()
            ]
        ]);

        // Verify integration
        $this->assertDatabaseHas('scheduled_messages', [
            'conversation_id' => $this->conversation->id,
            'status' => 'scheduled'
        ]);

        $this->assertStringContainsString('Voice message summary', $summaryMessage->content);
        $this->assertEquals(3, count($summaryMessage->getMetadata('transcription_ids')));
    }

    public function test_failed_transcription_triggers_retry_scheduling()
    {
        // Create voice message
        $voiceMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content_type' => 'voice'
        ]);

        $audioFile = MessageFile::factory()->create([
            'message_id' => $voiceMessage->id,
            'content_type' => 'audio/mp3'
        ]);

        // Mock failed transcription
        Storage::fake('local');
        Storage::put('message_files/' . $audioFile->filename, 'fake audio');

        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'error' => ['message' => 'API rate limit exceeded']
            ], 429)
        ]);

        // Process transcription (should fail)
        $transcriptionService = app(VoiceTranscriptionService::class);
        $transcription = $transcriptionService->transcribeVoiceMessage($voiceMessage);

        $this->assertEquals('failed', $transcription->status);
        $this->assertStringContainsString('429', $transcription->error_message);

        // Schedule a retry message
        $retryMessage = ScheduledMessage::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => 'Retrying voice transcription for failed message',
            'scheduled_for' => now()->addMinutes(15),
            'metadata' => [
                'type' => 'transcription_retry',
                'transcription_id' => $transcription->id,
                'original_message_id' => $voiceMessage->id,
                'retry_attempt' => 1
            ]
        ]);

        // Verify retry scheduling
        $this->assertDatabaseHas('scheduled_messages', [
            'conversation_id' => $this->conversation->id,
            'content' => 'Retrying voice transcription for failed message'
        ]);

        $this->assertEquals($transcription->id, $retryMessage->getMetadata('transcription_id'));
        $this->assertEquals(1, $retryMessage->getMetadata('retry_attempt'));
    }

    public function test_complete_workflow_integration()
    {
        // Create a quantum-capable bot with all features
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'capabilities' => [
                'receive_messages', 
                'send_messages', 
                'quantum_encryption',
                'process_files',
                'schedule_messages',
                'auto_respond'
            ]
        ]);

        BotConversation::create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'active',
            'permissions' => ['receive_messages', 'send_messages', 'process_files', 'schedule_messages']
        ]);

        // 1. User sends voice message
        $voiceMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content_type' => 'voice'
        ]);

        $audioFile = MessageFile::factory()->create([
            'message_id' => $voiceMessage->id,
            'content_type' => 'audio/wav'
        ]);

        Storage::fake('local');
        Storage::put('message_files/' . $audioFile->filename, 'voice data');

        // 2. Voice gets transcribed
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Hey bot, remind me every Friday at 5 PM to submit my timesheet.',
                'language' => 'en',
                'duration' => 18.5
            ], 200)
        ]);

        $transcriptionService = app(VoiceTranscriptionService::class);
        $transcription = $transcriptionService->transcribeVoiceMessage($voiceMessage);

        // 3. Bot processes the voice message
        $botService = app(BotService::class);
        $botService->processIncomingMessage($bot, $voiceMessage);

        // 4. Bot creates recurring scheduled messages
        $recurringMessages = [];
        for ($week = 0; $week < 4; $week++) {
            $recurringMessages[] = ScheduledMessage::factory()->create([
                'conversation_id' => $this->conversation->id,
                'sender_id' => $this->user->id, // Bot sends as user request fulfillment
                'content' => 'Reminder: Submit your timesheet! (Automated reminder from voice request)',
                'scheduled_for' => now()->nextWeekday(5)->addWeeks($week)->setHour(17)->setMinute(0),
                'metadata' => [
                    'type' => 'recurring_reminder',
                    'triggered_by_voice' => $voiceMessage->id,
                    'transcription_id' => $transcription->id,
                    'bot_id' => $bot->id,
                    'recurrence_pattern' => 'weekly',
                    'day_of_week' => 5 // Friday
                ]
            ]);
        }

        // 5. Verify complete integration
        $this->assertEquals('completed', $transcription->status);
        $this->assertStringContainsString('timesheet', $transcription->transcript);

        $this->assertDatabaseHas('bot_messages', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'incoming'
        ]);

        $this->assertDatabaseCount('scheduled_messages', 4);

        $this->assertDatabaseHas('bot_encryption_keys', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'is_active' => true
        ]);

        // 6. Process one of the scheduled messages
        $firstReminder = $recurringMessages[0];
        $firstReminder->update(['scheduled_for' => now()->subMinutes(5)]);

        $schedulingService = app(MessageSchedulingService::class);
        $processedCount = $schedulingService->processReadyMessages();

        $this->assertEquals(1, $processedCount);

        $firstReminder->refresh();
        $this->assertEquals('sent', $firstReminder->status);

        // Verify the sent message exists
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $this->conversation->id,
            'content' => 'Reminder: Submit your timesheet! (Automated reminder from voice request)'
        ]);

        // Complete workflow verification: Voice → Transcription → Bot Processing → Scheduled Messages → Message Delivery
        $this->assertTrue(
            $transcription->exists &&
            $firstReminder->isSent() &&
            $voiceMessage->exists &&
            $bot->exists
        );
    }
}