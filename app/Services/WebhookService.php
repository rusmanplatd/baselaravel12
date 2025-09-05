<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WebhookService
{
    /**
     * Available webhook events
     */
    public const EVENTS = [
        // Chat events
        'chat.message.sent',
        'chat.message.edited',
        'chat.message.deleted',
        'chat.conversation.created',
        'chat.conversation.updated',
        'chat.participant.added',
        'chat.participant.removed',
        'chat.call.started',
        'chat.call.ended',
        'chat.file.uploaded',
        'chat.poll.created',
        'chat.poll.voted',

        // User events
        'user.registered',
        'user.updated',
        'user.deactivated',

        // Security events
        'security.login.success',
        'security.login.failed',
        'security.password.changed',
        'security.device.registered',
        'security.suspicious.activity',

        // Organization events
        'organization.created',
        'organization.updated',
        'organization.member.added',
        'organization.member.removed',

        // System events
        'system.backup.completed',
        'system.maintenance.started',
        'system.maintenance.completed',
    ];

    /**
     * Trigger webhook for an event
     */
    public function trigger(string $event, array $payload, ?string $organizationId = null): void
    {
        $webhooks = Webhook::where('status', 'active')
            ->when($organizationId, fn($query) => $query->where('organization_id', $organizationId))
            ->get()
            ->filter(fn($webhook) => $webhook->listensTo($event));

        foreach ($webhooks as $webhook) {
            $this->deliver($webhook, $event, $payload);
        }
    }

    /**
     * Deliver webhook payload
     */
    public function deliver(Webhook $webhook, string $event, array $payload): WebhookDelivery
    {
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event_type' => $event,
            'payload' => $this->formatPayload($event, $payload),
            'headers' => $this->generateHeaders($webhook, $payload),
            'status' => 'pending',
        ]);

        try {
            $response = $this->sendRequest($webhook, $delivery);
            
            if ($response->successful()) {
                $delivery->update([
                    'status' => 'success',
                    'http_status' => $response->status(),
                    'response_body' => $response->body(),
                    'delivered_at' => now(),
                ]);
            } else {
                $this->handleFailedDelivery($delivery, $response);
            }

        } catch (\Exception $e) {
            $this->handleDeliveryException($delivery, $e);
        }

        return $delivery;
    }

    /**
     * Retry failed webhook delivery
     */
    public function retry(WebhookDelivery $delivery): bool
    {
        if (!$delivery->canRetry()) {
            return false;
        }

        $delivery->increment('attempt');
        $delivery->update(['status' => 'pending']);

        try {
            $response = $this->sendRequest($delivery->webhook, $delivery);
            
            if ($response->successful()) {
                $delivery->update([
                    'status' => 'success',
                    'http_status' => $response->status(),
                    'response_body' => $response->body(),
                    'delivered_at' => now(),
                ]);
                return true;
            } else {
                $this->handleFailedDelivery($delivery, $response);
                return false;
            }

        } catch (\Exception $e) {
            $this->handleDeliveryException($delivery, $e);
            return false;
        }
    }

    /**
     * Process webhook retries
     */
    public function processRetries(): int
    {
        $deliveries = WebhookDelivery::where('status', 'failed')
            ->where('next_retry_at', '<=', now())
            ->with('webhook')
            ->get()
            ->filter(fn($delivery) => $delivery->canRetry());

        $processed = 0;
        foreach ($deliveries as $delivery) {
            if ($this->retry($delivery)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Send HTTP request to webhook URL
     */
    private function sendRequest(Webhook $webhook, WebhookDelivery $delivery): Response
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Laravel-Webhooks/1.0',
            'X-Webhook-Signature' => $this->generateSignature($webhook, $delivery->payload),
            'X-Webhook-Event' => $delivery->event_type,
            'X-Webhook-Delivery' => $delivery->id,
        ], $webhook->headers ?? []);

        return Http::timeout($webhook->timeout)
            ->withHeaders($headers)
            ->post($webhook->url, $delivery->payload);
    }

    /**
     * Format webhook payload
     */
    private function formatPayload(string $event, array $payload): array
    {
        return [
            'event' => $event,
            'timestamp' => now()->toISOString(),
            'data' => $payload,
            'version' => '1.0',
        ];
    }

    /**
     * Generate headers for webhook request
     */
    private function generateHeaders(Webhook $webhook, array $payload): array
    {
        return [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Laravel-Webhooks/1.0',
            'X-Webhook-Signature' => $this->generateSignature($webhook, $payload),
        ];
    }

    /**
     * Generate HMAC signature for webhook payload
     */
    private function generateSignature(Webhook $webhook, array $payload): string
    {
        if (!$webhook->secret) {
            return '';
        }

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $webhook->secret);
        
        return 'sha256=' . $signature;
    }

    /**
     * Validate webhook signature
     */
    public function validateSignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle failed webhook delivery
     */
    private function handleFailedDelivery(WebhookDelivery $delivery, Response $response): void
    {
        $delivery->update([
            'status' => 'failed',
            'http_status' => $response->status(),
            'response_body' => $response->body(),
            'error_message' => 'HTTP ' . $response->status() . ' error',
            'next_retry_at' => $this->calculateNextRetry($delivery->attempt),
        ]);

        Log::warning('Webhook delivery failed', [
            'delivery_id' => $delivery->id,
            'webhook_id' => $delivery->webhook_id,
            'event_type' => $delivery->event_type,
            'http_status' => $response->status(),
            'attempt' => $delivery->attempt,
        ]);
    }

    /**
     * Handle webhook delivery exception
     */
    private function handleDeliveryException(WebhookDelivery $delivery, \Exception $e): void
    {
        $delivery->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'next_retry_at' => $this->calculateNextRetry($delivery->attempt),
        ]);

        Log::error('Webhook delivery exception', [
            'delivery_id' => $delivery->id,
            'webhook_id' => $delivery->webhook_id,
            'event_type' => $delivery->event_type,
            'error' => $e->getMessage(),
            'attempt' => $delivery->attempt,
        ]);
    }

    /**
     * Calculate next retry time using exponential backoff
     */
    private function calculateNextRetry(int $attempt): Carbon
    {
        $delay = min(pow(2, $attempt) * 60, 3600); // Max 1 hour delay
        return now()->addSeconds($delay);
    }
}