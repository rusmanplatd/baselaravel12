<?php

namespace Tests\Feature;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\MessageFile;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoiceTranscription;
use App\Services\VoiceTranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoiceTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Conversation $conversation;
    protected Message $message;
    protected MessageFile $audioFile;

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

        $this->message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content_type' => 'voice'
        ]);

        $this->audioFile = MessageFile::factory()->create([
            'message_id' => $this->message->id,
            'content_type' => 'audio/mp3',
            'file_size' => 1024 * 1024, // 1MB
            'duration' => 30.5 // 30.5 seconds
        ]);
    }

    public function test_can_start_transcription()
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/voice-transcriptions/transcribe', [
                'message_id' => $this->message->id
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'transcription' => [
                'id',
                'message_id',
                'status',
                'provider',
                'created_at'
            ],
            'message'
        ]);

        $this->assertDatabaseHas('voice_transcriptions', [
            'message_id' => $this->message->id,
            'attachment_id' => $this->audioFile->id,
            'status' => 'processing',
            'provider' => 'openai-whisper'
        ]);
    }

    public function test_openai_whisper_transcription()
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Hello, this is a test transcription.',
                'language' => 'en',
                'duration' => 30.5,
                'segments' => [
                    [
                        'id' => 0,
                        'start' => 0.0,
                        'end' => 5.5,
                        'text' => 'Hello, this is a test',
                        'avg_logprob' => -0.2,
                        'compression_ratio' => 1.5,
                        'no_speech_prob' => 0.01
                    ],
                    [
                        'id' => 1,
                        'start' => 5.5,
                        'end' => 30.5,
                        'text' => ' transcription.',
                        'avg_logprob' => -0.15,
                        'compression_ratio' => 1.2,
                        'no_speech_prob' => 0.02
                    ]
                ]
            ], 200)
        ]);

        Storage::fake('local');
        Storage::put('message_files/' . $this->audioFile->filename, 'fake audio content');

        $transcriptionService = app(VoiceTranscriptionService::class);
        $transcription = $transcriptionService->transcribeVoiceMessage($this->message);

        $this->assertNotNull($transcription);
        $this->assertEquals('completed', $transcription->status);
        $this->assertEquals('Hello, this is a test transcription.', $transcription->transcript);
        $this->assertEquals('en', $transcription->language);
        $this->assertEquals(30.5, $transcription->duration);
        $this->assertEquals(6, $transcription->word_count);
        $this->assertNotNull($transcription->segments);
        $this->assertGreaterThan(50, $transcription->confidence); // Should have reasonable confidence

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/audio/transcriptions' &&
                   $request->hasHeader('Authorization') &&
                   $request['model'] === 'whisper-1';
        });
    }

    public function test_can_get_transcription_status()
    {
        $transcription = VoiceTranscription::factory()->create([
            'message_id' => $this->message->id,
            'attachment_id' => $this->audioFile->id,
            'status' => 'processing'
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->getJson("/api/v1/voice-transcriptions/status/{$this->message->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => ['status' => 'processing'],
            'transcription' => [
                'id' => $transcription->id,
                'message_id' => $this->message->id,
                'status' => 'processing'
            ]
        ]);
    }

    public function test_can_retry_failed_transcription()
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Retry transcription successful.',
                'language' => 'en',
                'duration' => 15.0
            ], 200)
        ]);

        Storage::fake('local');
        Storage::put('message_files/' . $this->audioFile->filename, 'fake audio content');

        $transcription = VoiceTranscription::factory()->create([
            'message_id' => $this->message->id,
            'attachment_id' => $this->audioFile->id,
            'status' => 'failed',
            'error_message' => 'Previous error',
            'retry_count' => 1
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson("/api/v1/voice-transcriptions/{$transcription->id}/retry");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'transcription' => [
                'id',
                'status',
                'transcript',
                'retry_count'
            ],
            'message'
        ]);

        $transcription->refresh();
        $this->assertEquals('completed', $transcription->status);
        $this->assertEquals('Retry transcription successful.', $transcription->transcript);
        $this->assertEquals(2, $transcription->retry_count);
    }

    public function test_bulk_transcription()
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Bulk transcription test.',
                'language' => 'en',
                'duration' => 10.0
            ], 200)
        ]);

        Storage::fake('local');

        // Create additional messages and files
        $message2 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content_type' => 'voice'
        ]);

        $audioFile2 = MessageFile::factory()->create([
            'message_id' => $message2->id,
            'content_type' => 'audio/wav'
        ]);

        Storage::put('message_files/' . $this->audioFile->filename, 'fake audio 1');
        Storage::put('message_files/' . $audioFile2->filename, 'fake audio 2');

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/voice-transcriptions/bulk-transcribe', [
                'message_ids' => [$this->message->id, $message2->id]
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'results',
            'summary' => [
                'total',
                'successful',
                'failed'
            ],
            'message'
        ]);

        $this->assertDatabaseCount('voice_transcriptions', 2);
    }

    public function test_can_search_transcriptions()
    {
        VoiceTranscription::factory()->create([
            'message_id' => $this->message->id,
            'attachment_id' => $this->audioFile->id,
            'status' => 'completed',
            'transcript' => 'This is a test audio message about technology.',
            'language' => 'en',
            'confidence' => 85.5
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->getJson('/api/v1/voice-transcriptions/search?' . http_build_query([
                'query' => 'technology',
                'language' => 'en',
                'min_confidence' => 80
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'transcriptions' => [
                '*' => [
                    'id',
                    'transcript',
                    'language',
                    'confidence_percentage',
                    'created_at'
                ]
            ],
            'pagination'
        ]);

        $transcriptions = $response->json('transcriptions');
        $this->assertCount(1, $transcriptions);
        $this->assertStringContainsString('technology', $transcriptions[0]['transcript']);
    }

    public function test_can_get_transcription_statistics()
    {
        VoiceTranscription::factory()->count(3)->create([
            'status' => 'completed',
            'language' => 'en',
            'provider' => 'openai-whisper'
        ]);

        VoiceTranscription::factory()->create([
            'status' => 'failed',
            'language' => 'es',
            'provider' => 'openai-whisper'
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->getJson('/api/v1/voice-transcriptions/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'statistics' => [
                'total_transcriptions',
                'completed',
                'failed',
                'processing',
                'pending',
                'languages',
                'providers',
                'average_confidence',
                'total_duration',
                'total_words'
            ]
        ]);

        $stats = $response->json('statistics');
        $this->assertEquals(4, $stats['total_transcriptions']);
        $this->assertEquals(3, $stats['completed']);
        $this->assertEquals(1, $stats['failed']);
    }

    public function test_non_voice_message_returns_error()
    {
        $textMessage = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content_type' => 'text',
            'content' => 'This is a text message'
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/voice-transcriptions/transcribe', [
                'message_id' => $textMessage->id
            ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Message is not a voice message']);
    }

    public function test_unsupported_audio_format_fails()
    {
        $unsupportedFile = MessageFile::factory()->create([
            'message_id' => $this->message->id,
            'content_type' => 'audio/flac' // Unsupported format
        ]);

        Storage::fake('local');
        Storage::put('message_files/' . $unsupportedFile->filename, 'fake flac content');

        $transcriptionService = app(VoiceTranscriptionService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported audio format');

        $transcriptionService->transcribeVoiceMessage($this->message);
    }

    public function test_file_too_large_fails()
    {
        $largeFile = MessageFile::factory()->create([
            'message_id' => $this->message->id,
            'content_type' => 'audio/mp3',
            'file_size' => 30 * 1024 * 1024 // 30MB - exceeds 25MB limit
        ]);

        Storage::fake('local');
        // Create a mock large file
        Storage::put('message_files/' . $largeFile->filename, str_repeat('x', 30 * 1024 * 1024));

        $transcriptionService = app(VoiceTranscriptionService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Audio file too large');

        $transcriptionService->transcribeVoiceMessage($this->message);
    }

    public function test_openai_api_error_handling()
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'error' => ['message' => 'API rate limit exceeded']
            ], 429)
        ]);

        Storage::fake('local');
        Storage::put('message_files/' . $this->audioFile->filename, 'fake audio content');

        $transcriptionService = app(VoiceTranscriptionService::class);
        $transcription = $transcriptionService->transcribeVoiceMessage($this->message);

        $this->assertEquals('failed', $transcription->status);
        $this->assertStringContainsString('429', $transcription->error_message);
    }

    public function test_confidence_calculation()
    {
        $segments = [
            ['avg_logprob' => -0.1], // High confidence
            ['avg_logprob' => -0.5], // Medium confidence
            ['avg_logprob' => -1.0], // Lower confidence
        ];

        $transcriptionService = app(VoiceTranscriptionService::class);
        $reflection = new \ReflectionClass($transcriptionService);
        $method = $reflection->getMethod('calculateAverageConfidence');
        $method->setAccessible(true);

        $confidence = $method->invoke($transcriptionService, $segments);

        $this->assertIsFloat($confidence);
        $this->assertGreaterThan(0, $confidence);
        $this->assertLessThan(100, $confidence);
    }

    public function test_unauthorized_access_denied()
    {
        $otherUser = User::factory()->create();
        $otherConversation = Conversation::factory()->create();

        $otherMessage = Message::factory()->create([
            'conversation_id' => $otherConversation->id,
            'content_type' => 'voice'
        ]);

        $response = $this->actingAs($otherUser)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/voice-transcriptions/transcribe', [
                'message_id' => $otherMessage->id
            ]);

        $response->assertStatus(404); // Message not found in accessible conversations
    }
}