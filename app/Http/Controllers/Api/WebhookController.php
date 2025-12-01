<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookService $webhookService
    ) {
        $this->middleware('auth:api');
        $this->middleware('chat.permission:chat.webhooks.manage')->except(['index', 'show']);
        $this->middleware('throttle:60,1');
    }

    /**
     * List webhooks for organization
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get user's organization (assuming middleware sets this)
        $organizationId = $request->header('X-Organization-Id');
        
        if (!$organizationId) {
            return response()->json(['error' => 'Organization context required'], 400);
        }

        $webhooks = Webhook::where('organization_id', $organizationId)
            ->with(['creator:id,name'])
            ->withCount(['deliveries as total_deliveries'])
            ->withCount(['deliveries as successful_deliveries' => function ($query) {
                $query->where('status', 'success');
            }])
            ->orderByDesc('created_at')
            ->paginate(20);

        // Add success rate to each webhook
        $webhooks->getCollection()->transform(function ($webhook) {
            $webhook->success_rate = $webhook->getSuccessRate();
            return $webhook;
        });

        return response()->json([
            'webhooks' => $webhooks->items(),
            'pagination' => [
                'current_page' => $webhooks->currentPage(),
                'last_page' => $webhooks->lastPage(),
                'per_page' => $webhooks->perPage(),
                'total' => $webhooks->total(),
            ],
        ]);
    }

    /**
     * Create new webhook
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:' . implode(',', WebhookService::EVENTS),
            'retry_attempts' => 'integer|min:0|max:10',
            'timeout' => 'integer|min:5|max:120',
            'headers' => 'nullable|array',
            'headers.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        if (!$organizationId) {
            return response()->json(['error' => 'Organization context required'], 400);
        }

        $webhook = Webhook::create([
            'name' => $request->name,
            'url' => $request->url,
            'events' => $request->events,
            'retry_attempts' => $request->input('retry_attempts', 3),
            'timeout' => $request->input('timeout', 30),
            'headers' => $request->headers,
            'organization_id' => $organizationId,
            'created_by' => $request->user()->id,
        ]);

        // Generate secret
        $webhook->generateSecret();

        $webhook->load('creator:id,name');

        return response()->json([
            'webhook' => $webhook,
            'message' => 'Webhook created successfully',
        ], 201);
    }

    /**
     * Show webhook details
     */
    public function show(Request $request, string $webhookId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $webhook = Webhook::where('id', $webhookId)
            ->where('organization_id', $organizationId)
            ->with(['creator:id,name'])
            ->withCount(['deliveries as total_deliveries'])
            ->withCount(['deliveries as successful_deliveries' => function ($query) {
                $query->where('status', 'success');
            }])
            ->firstOrFail();

        $webhook->success_rate = $webhook->getSuccessRate();

        return response()->json(['webhook' => $webhook]);
    }

    /**
     * Update webhook
     */
    public function update(Request $request, string $webhookId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'url' => 'url|max:2048',
            'events' => 'array|min:1',
            'events.*' => 'string|in:' . implode(',', WebhookService::EVENTS),
            'status' => 'in:active,inactive,disabled',
            'retry_attempts' => 'integer|min:0|max:10',
            'timeout' => 'integer|min:5|max:120',
            'headers' => 'nullable|array',
            'headers.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organizationId = $request->header('X-Organization-Id');
        
        $webhook = Webhook::where('id', $webhookId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $webhook->update($request->only([
            'name', 'url', 'events', 'status', 
            'retry_attempts', 'timeout', 'headers'
        ]));

        $webhook->load('creator:id,name');

        return response()->json([
            'webhook' => $webhook,
            'message' => 'Webhook updated successfully',
        ]);
    }

    /**
     * Delete webhook
     */
    public function destroy(Request $request, string $webhookId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $webhook = Webhook::where('id', $webhookId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $webhook->delete();

        return response()->json(['message' => 'Webhook deleted successfully']);
    }

    /**
     * Regenerate webhook secret
     */
    public function regenerateSecret(Request $request, string $webhookId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $webhook = Webhook::where('id', $webhookId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $secret = $webhook->generateSecret();

        return response()->json([
            'secret' => $secret,
            'message' => 'Secret regenerated successfully',
        ]);
    }

    /**
     * Test webhook delivery
     */
    public function test(Request $request, string $webhookId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $webhook = Webhook::where('id', $webhookId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        if (!$webhook->isActive()) {
            return response()->json(['error' => 'Webhook is not active'], 400);
        }

        // Send test event
        $testPayload = [
            'test' => true,
            'webhook_id' => $webhook->id,
            'timestamp' => now()->toISOString(),
            'message' => 'This is a test webhook delivery',
        ];

        $delivery = $this->webhookService->deliver($webhook, 'webhook.test', $testPayload);

        return response()->json([
            'delivery' => $delivery,
            'message' => 'Test webhook sent',
        ]);
    }

    /**
     * Get webhook deliveries
     */
    public function deliveries(Request $request, string $webhookId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $webhook = Webhook::where('id', $webhookId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $deliveries = $webhook->deliveries()
            ->when($request->status, fn($query) => $query->where('status', $request->status))
            ->when($request->event_type, fn($query) => $query->where('event_type', $request->event_type))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'deliveries' => $deliveries->items(),
            'pagination' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
            ],
        ]);
    }

    /**
     * Retry failed delivery
     */
    public function retryDelivery(Request $request, string $webhookId, string $deliveryId): JsonResponse
    {
        $organizationId = $request->header('X-Organization-Id');
        
        $webhook = Webhook::where('id', $webhookId)
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $delivery = $webhook->deliveries()
            ->where('id', $deliveryId)
            ->firstOrFail();

        if (!$delivery->canRetry()) {
            return response()->json(['error' => 'Delivery cannot be retried'], 400);
        }

        $success = $this->webhookService->retry($delivery);

        return response()->json([
            'delivery' => $delivery->fresh(),
            'success' => $success,
            'message' => $success ? 'Delivery retried successfully' : 'Delivery retry failed',
        ]);
    }

    /**
     * Get webhook events list
     */
    public function events(): JsonResponse
    {
        $events = collect(WebhookService::EVENTS)->map(function ($event) {
            [$category, $action, $detail] = array_pad(explode('.', $event), 3, null);
            
            return [
                'event' => $event,
                'category' => $category,
                'action' => $action,
                'detail' => $detail,
                'description' => $this->getEventDescription($event),
            ];
        })->groupBy('category');

        return response()->json(['events' => $events]);
    }

    /**
     * Get event description
     */
    private function getEventDescription(string $event): string
    {
        $descriptions = [
            'chat.message.sent' => 'Triggered when a message is sent in a conversation',
            'chat.message.edited' => 'Triggered when a message is edited',
            'chat.message.deleted' => 'Triggered when a message is deleted',
            'chat.conversation.created' => 'Triggered when a new conversation is created',
            'chat.conversation.updated' => 'Triggered when conversation settings are updated',
            'chat.participant.added' => 'Triggered when a participant joins a conversation',
            'chat.participant.removed' => 'Triggered when a participant leaves a conversation',
            'chat.call.started' => 'Triggered when a video/audio call starts',
            'chat.call.ended' => 'Triggered when a video/audio call ends',
            'chat.file.uploaded' => 'Triggered when a file is uploaded to a conversation',
            'chat.poll.created' => 'Triggered when a poll is created',
            'chat.poll.voted' => 'Triggered when someone votes in a poll',
            'user.registered' => 'Triggered when a new user registers',
            'user.updated' => 'Triggered when user profile is updated',
            'user.deactivated' => 'Triggered when a user account is deactivated',
            'security.login.success' => 'Triggered on successful login',
            'security.login.failed' => 'Triggered on failed login attempt',
            'security.password.changed' => 'Triggered when user changes password',
            'security.device.registered' => 'Triggered when a new device is registered',
            'security.suspicious.activity' => 'Triggered when suspicious activity is detected',
        ];

        return $descriptions[$event] ?? 'No description available';
    }
}