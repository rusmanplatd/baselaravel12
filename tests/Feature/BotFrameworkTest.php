<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Chat\BotConversation;
use App\Models\Chat\BotMessage;
use App\Models\Chat\BotEncryptionKey;
use App\Models\Chat\Conversation;
use App\Models\Organization;
use App\Models\User;
use App\Services\BotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotFrameworkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organization $organization;
    protected Conversation $conversation;
    protected Bot $bot;

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

    public function test_can_create_bot()
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson('/api/v1/bots', [
                'name' => 'Test Bot',
                'description' => 'A test bot for automated testing',
                'webhook_url' => 'https://example.com/webhook',
                'capabilities' => ['receive_messages', 'send_messages', 'quantum_encryption'],
                'rate_limit_per_minute' => 60,
                'configuration' => [
                    'encryption' => ['preferred_algorithm' => 'ML-KEM-768']
                ]
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'bot' => [
                'id',
                'name',
                'description',
                'webhook_url',
                'capabilities',
                'configuration',
                'is_active',
                'created_at'
            ],
            'api_token',
            'webhook_secret',
            'message'
        ]);

        $this->assertDatabaseHas('bots', [
            'name' => 'Test Bot',
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id
        ]);
    }

    public function test_bot_api_token_is_generated()
    {
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id
        ]);

        $token = $bot->generateApiToken();

        $this->assertStringStartsWith('bot_', $token);
        $this->assertEquals(68, strlen($token)); // 'bot_' + 64 random chars
        $this->assertDatabaseHas('bots', [
            'id' => $bot->id,
            'api_token' => $token
        ]);
    }

    public function test_can_add_bot_to_conversation()
    {
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'capabilities' => ['receive_messages', 'send_messages', 'quantum_encryption']
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->postJson("/api/v1/bots/{$bot->id}/add-to-conversation", [
                'conversation_id' => $this->conversation->id,
                'permissions' => ['read_messages', 'send_messages']
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'bot_conversation' => [
                'id',
                'bot_id',
                'conversation_id',
                'status',
                'permissions'
            ],
            'message'
        ]);

        $this->assertDatabaseHas('bot_conversations', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'active'
        ]);
    }

    public function test_quantum_capable_bot_creates_encryption_keys()
    {
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'capabilities' => ['quantum_encryption']
        ]);

        $botService = app(BotService::class);
        $botConversation = $botService->addBotToConversation($bot, $this->conversation);

        // Check that encryption keys were created
        $this->assertDatabaseHas('bot_encryption_keys', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'is_active' => true
        ]);

        $encryptionKey = BotEncryptionKey::where('bot_id', $bot->id)
            ->where('conversation_id', $this->conversation->id)
            ->first();

        $this->assertNotNull($encryptionKey);
        $this->assertTrue($encryptionKey->isActive());
        $this->assertContains($encryptionKey->algorithm, ['ML-KEM-768', 'RSA-4096-OAEP']);
    }

    public function test_can_send_message_as_bot()
    {
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'capabilities' => ['send_messages']
        ]);

        $botConversation = BotConversation::create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'active',
            'permissions' => ['send_messages']
        ]);

        $response = $this->postJson("/api/v1/bots/{$bot->id}/send-message", [
            'conversation_id' => $this->conversation->id,
            'content' => 'Hello from bot!',
            'content_type' => 'text'
        ], [
            'Authorization' => 'Bearer ' . $bot->api_token,
            'X-Organization-Id' => $this->organization->id
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message' => [
                'id',
                'conversation_id',
                'content',
                'sender_bot_id',
                'created_at'
            ],
            'success'
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_bot_id' => $bot->id,
            'content' => 'Hello from bot!'
        ]);

        $this->assertDatabaseHas('bot_messages', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'outgoing',
            'content' => 'Hello from bot!'
        ]);
    }

    public function test_webhook_signature_validation()
    {
        $bot = Bot::factory()->create([
            'webhook_secret' => 'whsec_test123'
        ]);

        $payload = ['test' => 'data'];
        $payloadJson = json_encode($payload);
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payloadJson, 'whsec_test123');

        $botService = app(BotService::class);
        $isValid = $botService->validateBotSignature($bot, $payloadJson, $expectedSignature);

        $this->assertTrue($isValid);

        // Test invalid signature
        $invalidSignature = 'sha256=invalid';
        $isInvalid = $botService->validateBotSignature($bot, $payloadJson, $invalidSignature);

        $this->assertFalse($isInvalid);
    }

    public function test_bot_webhook_delivery()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['success' => true], 200)
        ]);

        $bot = Bot::factory()->create([
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => 'whsec_test123'
        ]);

        $message = \App\Models\Chat\Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->user->id,
            'content' => 'Test message'
        ]);

        $botService = app(BotService::class);
        $botService->processIncomingMessage($bot, $message);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook' &&
                   $request->hasHeader('X-Bot-Signature') &&
                   $request['event'] === 'message.received';
        });
    }

    public function test_bot_rate_limiting()
    {
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'rate_limit_per_minute' => 2
        ]);

        $botConversation = BotConversation::create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'active',
            'permissions' => ['send_messages']
        ]);

        // First two requests should succeed
        for ($i = 0; $i < 2; $i++) {
            $response = $this->postJson("/api/v1/bots/{$bot->id}/send-message", [
                'conversation_id' => $this->conversation->id,
                'content' => "Message $i"
            ], [
                'Authorization' => 'Bearer ' . $bot->api_token
            ]);

            $response->assertStatus(200);
        }

        // Third request should be rate limited
        $response = $this->postJson("/api/v1/bots/{$bot->id}/send-message", [
            'conversation_id' => $this->conversation->id,
            'content' => 'Rate limited message'
        ], [
            'Authorization' => 'Bearer ' . $bot->api_token
        ]);

        $response->assertStatus(429);
        $response->assertJson(['error' => 'Rate limit exceeded']);
    }

    public function test_can_remove_bot_from_conversation()
    {
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id
        ]);

        $botConversation = BotConversation::create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'active'
        ]);

        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->deleteJson("/api/v1/bots/{$bot->id}/conversations/{$this->conversation->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('bot_conversations', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'status' => 'removed'
        ]);

        // Check encryption keys are deactivated
        $this->assertDatabaseMissing('bot_encryption_keys', [
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'is_active' => true
        ]);
    }

    public function test_can_get_bot_capabilities()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/bots/capabilities');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'capabilities' => [
                '*' => [
                    'capability',
                    'description',
                    'requires_quantum'
                ]
            ]
        ]);

        $capabilities = $response->json('capabilities');
        $capabilityNames = collect($capabilities)->pluck('capability')->toArray();

        $this->assertContains('receive_messages', $capabilityNames);
        $this->assertContains('send_messages', $capabilityNames);
        $this->assertContains('quantum_encryption', $capabilityNames);
    }

    public function test_bot_encryption_key_expiration()
    {
        $bot = Bot::factory()->create();
        
        $expiredKey = BotEncryptionKey::factory()->create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'expires_at' => now()->subDay(),
            'is_active' => true
        ]);

        $activeKey = BotEncryptionKey::factory()->create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversation->id,
            'expires_at' => now()->addDay(),
            'is_active' => true
        ]);

        $this->assertTrue($expiredKey->isExpired());
        $this->assertFalse($expiredKey->isActive());
        
        $this->assertFalse($activeKey->isExpired());
        $this->assertTrue($activeKey->isActive());
    }

    public function test_unauthorized_access_denied()
    {
        $bot = Bot::factory()->create([
            'organization_id' => $this->organization->id
        ]);

        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->withHeaders(['X-Organization-Id' => $this->organization->id])
            ->getJson("/api/v1/bots/{$bot->id}");

        $response->assertStatus(403);
    }

    public function test_invalid_bot_token_rejected()
    {
        $response = $this->postJson('/api/v1/bots/invalid-id/send-message', [
            'conversation_id' => $this->conversation->id,
            'content' => 'Test message'
        ], [
            'Authorization' => 'Bearer invalid_token'
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid bot token']);
    }
}