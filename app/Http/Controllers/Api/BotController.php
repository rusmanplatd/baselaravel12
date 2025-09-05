<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Chat\Conversation;
use App\Services\BotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BotController extends Controller
{
    public function __construct(
        private BotService $botService
    ) {
        $this->middleware('auth:api');
        $this->middleware('chat.permission:bots.manage')->except(['index', 'show']);
        $this->middleware('throttle:60,1');
    }

    /**
     * List bots for organization
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        if (!$organizationId) {
            return response()->json(['error' => 'Organization context required'], 400);
        }

        $bots = Bot::where('organization_id', $organizationId)
            ->with(['creator:id,name'])
            ->withCount(['conversations as active_conversations' => function ($query) {
                $query->where('status', 'active');
            }])
            ->withCount(['messages as total_messages'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'bots' => $bots->items(),
            'pagination' => [
                'current_page' => $bots->currentPage(),
                'last_page' => $bots->lastPage(),
                'per_page' => $bots->perPage(),
                'total' => $bots->total(),
            ],
        ]);
    }

    /**
     * Create new bot
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'avatar' => 'nullable|url|max:2048',
            'webhook_url' => 'nullable|url|max:2048',
            'capabilities' => 'required|array|min:1',
            'capabilities.*' => 'string|in:' . implode(',', $this->getBotCapabilities()),
            'configuration' => 'nullable|array',
            'rate_limit_per_minute' => 'integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        if (!$organizationId) {
            return response()->json(['error' => 'Organization context required'], 400);
        }

        $bot = Bot::create([
            'name' => $request->name,
            'description' => $request->description,
            'avatar' => $request->avatar,
            'webhook_url' => $request->webhook_url,
            'capabilities' => $request->capabilities,
            'configuration' => $request->configuration ?? [],
            'rate_limit_per_minute' => $request->input('rate_limit_per_minute', 60),
            'organization_id' => $organizationId,
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]);

        // Generate API token and webhook secret
        $bot->generateApiToken();
        if ($bot->webhook_url) {
            $bot->generateWebhookSecret();
        }

        $bot->load('creator:id,name');

        return response()->json([
            'bot' => $bot,
            'api_token' => $bot->api_token,
            'webhook_secret' => $bot->webhook_secret,
            'message' => 'Bot created successfully',
        ], 201);
    }

    /**
     * Show bot details
     */
    public function show(Request $request, string $botId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $bot = Bot::where('id', $botId)
            ->where('organization_id', $organizationId)
            ->with(['creator:id,name'])
            ->withCount(['conversations as active_conversations' => function ($query) {
                $query->where('status', 'active');
            }])
            ->withCount(['messages as total_messages'])
            ->firstOrFail();

        return response()->json(['bot' => $bot]);
    }

    /**
     * Update bot
     */
    public function update(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string|max:1000',
            'avatar' => 'nullable|url|max:2048',
            'webhook_url' => 'nullable|url|max:2048',
            'capabilities' => 'array|min:1',
            'capabilities.*' => 'string|in:' . implode(',', $this->getBotCapabilities()),
            'configuration' => 'nullable|array',
            'rate_limit_per_minute' => 'integer|min:1|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        
        $bot = Bot::where('id', $botId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $bot->update($request->only([
            'name', 'description', 'avatar', 'webhook_url', 
            'capabilities', 'configuration', 'rate_limit_per_minute', 'is_active'
        ]));

        // Generate webhook secret if URL was added
        if ($request->webhook_url && !$bot->webhook_secret) {
            $bot->generateWebhookSecret();
        }

        $bot->load('creator:id,name');

        return response()->json([
            'bot' => $bot,
            'message' => 'Bot updated successfully',
        ]);
    }

    /**
     * Delete bot
     */
    public function destroy(Request $request, string $botId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $bot = Bot::where('id', $botId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        // Remove from all conversations
        $bot->conversations()->update(['status' => 'removed']);

        // Deactivate encryption keys
        $bot->encryptionKeys()->update(['is_active' => false]);

        $bot->delete();

        return response()->json(['message' => 'Bot deleted successfully']);
    }

    /**
     * Regenerate bot API token
     */
    public function regenerateToken(Request $request, string $botId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $bot = Bot::where('id', $botId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $token = $bot->generateApiToken();

        return response()->json([
            'api_token' => $token,
            'message' => 'API token regenerated successfully',
        ]);
    }

    /**
     * Regenerate webhook secret
     */
    public function regenerateWebhookSecret(Request $request, string $botId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $bot = Bot::where('id', $botId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if (!$bot->webhook_url) {
            return response()->json(['error' => 'Bot has no webhook URL configured'], 400);
        }

        $secret = $bot->generateWebhookSecret();

        return response()->json([
            'webhook_secret' => $secret,
            'message' => 'Webhook secret regenerated successfully',
        ]);
    }

    /**
     * Add bot to conversation
     */
    public function addToConversation(Request $request, string $botId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|string',
            'permissions' => 'array',
            'permissions.*' => 'string|in:read_messages,send_messages,read_history,manage_conversation',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        
        $bot = Bot::where('id', $botId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $conversation = Conversation::where('id', $request->conversation_id)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if (!$bot->isActive()) {
            return response()->json(['error' => 'Bot is not active'], 400);
        }

        $botConversation = $this->botService->addBotToConversation(
            $bot, 
            $conversation, 
            $request->input('permissions', [])
        );

        return response()->json([
            'bot_conversation' => $botConversation,
            'message' => 'Bot added to conversation successfully',
        ]);
    }

    /**
     * Remove bot from conversation
     */
    public function removeFromConversation(Request $request, string $botId, string $conversationId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $bot = Bot::where('id', $botId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $conversation = Conversation::where('id', $conversationId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $this->botService->removeBotFromConversation($bot, $conversation);

        return response()->json(['message' => 'Bot removed from conversation successfully']);
    }

    /**
     * Send message as bot
     */
    public function sendMessage(Request $request, string $botId): JsonResponse
    {
        // This endpoint is for bot API authentication
        $this->middleware('auth:bot');

        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|string',
            'content' => 'required|string|max:10000',
            'content_type' => 'string|in:text,markdown,html',
            'metadata' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bot = $request->bot; // Set by auth:bot middleware
        
        $conversation = Conversation::where('id', $request->conversation_id)
            ->where('organization_id', $bot->organization_id)
            ->firstOrFail();

        $message = $this->botService->sendBotMessage($bot, $conversation, [
            'content' => $request->content,
            'content_type' => $request->input('content_type', 'text'),
            'metadata' => $request->input('metadata', []),
        ]);

        if (!$message) {
            return response()->json(['error' => 'Failed to send message'], 400);
        }

        return response()->json([
            'message' => $message,
            'success' => true,
        ]);
    }

    /**
     * Get bot capabilities list
     */
    public function capabilities(): JsonResponse
    {
        $capabilities = collect($this->getBotCapabilities())->map(function ($capability) {
            return [
                'capability' => $capability,
                'description' => $this->getCapabilityDescription($capability),
                'requires_quantum' => in_array($capability, ['quantum_encryption']),
            ];
        });

        return response()->json(['capabilities' => $capabilities]);
    }

    /**
     * Get available bot capabilities
     */
    private function getBotCapabilities(): array
    {
        return [
            'receive_messages',
            'send_messages',
            'read_history',
            'process_files',
            'quantum_encryption',
            'manage_conversation',
            'create_polls',
            'schedule_messages',
            'auto_respond',
            'sentiment_analysis',
            'language_translation',
            'message_moderation',
        ];
    }

    /**
     * Get capability description
     */
    private function getCapabilityDescription(string $capability): string
    {
        $descriptions = [
            'receive_messages' => 'Bot can receive and process incoming messages',
            'send_messages' => 'Bot can send messages to conversations',
            'read_history' => 'Bot can access conversation message history',
            'process_files' => 'Bot can process and analyze uploaded files',
            'quantum_encryption' => 'Bot supports quantum-resistant encryption',
            'manage_conversation' => 'Bot can modify conversation settings',
            'create_polls' => 'Bot can create and manage polls',
            'schedule_messages' => 'Bot can schedule messages for future delivery',
            'auto_respond' => 'Bot can automatically respond to messages',
            'sentiment_analysis' => 'Bot can analyze message sentiment',
            'language_translation' => 'Bot can translate messages between languages',
            'message_moderation' => 'Bot can moderate and filter messages',
        ];

        return $descriptions[$capability] ?? 'No description available';
    }
}