<?php

namespace App\Services;

use App\Models\ScheduledMessage;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class MessageSchedulingService
{
    public function __construct(
        private ChatEncryptionService $encryption
    ) {}

    /**
     * Schedule a message
     */
    public function scheduleMessage(
        Conversation $conversation,
        User $sender,
        string $content,
        Carbon $scheduledFor,
        string $contentType = 'text',
        string $timezone = 'UTC',
        array $metadata = []
    ): ScheduledMessage {
        // Validate schedule time
        if ($scheduledFor <= now()) {
            throw new \InvalidArgumentException('Scheduled time must be in the future');
        }

        // Check if user can send messages to this conversation
        if (!$conversation->hasParticipant($sender->id)) {
            throw new \Exception('User is not a participant in this conversation');
        }

        // Create scheduled message
        $scheduledMessage = ScheduledMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => $content,
            'content_type' => $contentType,
            'scheduled_for' => $scheduledFor,
            'timezone' => $timezone,
            'status' => 'scheduled',
            'retry_count' => 0,
            'max_retries' => 3,
            'metadata' => $metadata,
        ]);

        Log::info('Message scheduled', [
            'scheduled_message_id' => $scheduledMessage->id,
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'scheduled_for' => $scheduledFor->toISOString(),
        ]);

        // Fire event
        Event::dispatch('message.scheduled', $scheduledMessage);

        return $scheduledMessage;
    }

    /**
     * Process ready-to-send scheduled messages
     */
    public function processScheduledMessages(): int
    {
        $readyMessages = ScheduledMessage::readyToSend()
            ->with(['conversation', 'sender'])
            ->orderBy('scheduled_for')
            ->limit(100) // Process max 100 messages at a time
            ->get();

        $processed = 0;

        foreach ($readyMessages as $scheduledMessage) {
            try {
                $this->sendScheduledMessage($scheduledMessage);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to send scheduled message', [
                    'scheduled_message_id' => $scheduledMessage->id,
                    'error' => $e->getMessage(),
                ]);

                $scheduledMessage->markAsFailed($e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Send a scheduled message
     */
    public function sendScheduledMessage(ScheduledMessage $scheduledMessage): Message
    {
        if (!$scheduledMessage->isReadyToSend()) {
            throw new \Exception('Message is not ready to send');
        }

        $scheduledMessage->markAsSending();

        try {
            // Create the actual message
            $messageData = [
                'conversation_id' => $scheduledMessage->conversation_id,
                'sender_id' => $scheduledMessage->sender_id,
                'content' => $scheduledMessage->getContent(),
                'content_type' => $scheduledMessage->getContentType(),
                'metadata' => array_merge(
                    $scheduledMessage->getMetadata(),
                    [
                        'scheduled_message_id' => $scheduledMessage->id,
                        'originally_scheduled_for' => $scheduledMessage->scheduled_for->toISOString(),
                        'sent_via_scheduler' => true,
                    ]
                ),
            ];

            // Handle encryption if conversation is encrypted
            if ($scheduledMessage->conversation->isEncrypted()) {
                $messageData = $this->encryptScheduledMessage($scheduledMessage, $messageData);
            }

            $message = Message::create($messageData);

            // Mark scheduled message as sent
            $scheduledMessage->markAsSent($message->id);

            // Fire events
            Event::dispatch('message.sent', $message);
            Event::dispatch('scheduled_message.sent', $scheduledMessage, $message);

            Log::info('Scheduled message sent successfully', [
                'scheduled_message_id' => $scheduledMessage->id,
                'message_id' => $message->id,
            ]);

            return $message;

        } catch (\Exception $e) {
            $scheduledMessage->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel a scheduled message
     */
    public function cancelScheduledMessage(ScheduledMessage $scheduledMessage): void
    {
        if (!$scheduledMessage->canCancel()) {
            throw new \Exception('Cannot cancel this scheduled message');
        }

        $scheduledMessage->markAsCancelled();

        Event::dispatch('scheduled_message.cancelled', $scheduledMessage);

        Log::info('Scheduled message cancelled', [
            'scheduled_message_id' => $scheduledMessage->id,
        ]);
    }

    /**
     * Retry a failed scheduled message
     */
    public function retryScheduledMessage(ScheduledMessage $scheduledMessage, ?Carbon $newScheduledTime = null): void
    {
        if (!$scheduledMessage->canRetry()) {
            throw new \Exception('Cannot retry this scheduled message');
        }

        if ($newScheduledTime) {
            $scheduledMessage->reschedule($newScheduledTime);
        } else {
            $scheduledMessage->retry();
        }

        Event::dispatch('scheduled_message.retried', $scheduledMessage);

        Log::info('Scheduled message retry queued', [
            'scheduled_message_id' => $scheduledMessage->id,
            'retry_count' => $scheduledMessage->retry_count,
        ]);
    }

    /**
     * Reschedule a message
     */
    public function rescheduleMessage(ScheduledMessage $scheduledMessage, Carbon $newTime): void
    {
        if ($newTime <= now()) {
            throw new \InvalidArgumentException('New schedule time must be in the future');
        }

        $scheduledMessage->reschedule($newTime);

        Event::dispatch('scheduled_message.rescheduled', $scheduledMessage);

        Log::info('Scheduled message rescheduled', [
            'scheduled_message_id' => $scheduledMessage->id,
            'new_scheduled_for' => $newTime->toISOString(),
        ]);
    }

    /**
     * Get scheduled messages for a conversation
     */
    public function getScheduledMessagesForConversation(
        Conversation $conversation,
        ?User $user = null,
        array $statuses = []
    ): \Illuminate\Database\Eloquent\Collection {
        $query = ScheduledMessage::forConversation($conversation->id)
            ->with(['sender'])
            ->orderBy('scheduled_for');

        if ($user) {
            $query->forSender($user->id);
        }

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        return $query->get();
    }

    /**
     * Get scheduled messages for a user
     */
    public function getScheduledMessagesForUser(
        User $user,
        array $statuses = [],
        ?string $conversationId = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = ScheduledMessage::forSender($user->id)
            ->with(['conversation'])
            ->orderBy('scheduled_for');

        if ($conversationId) {
            $query->forConversation($conversationId);
        }

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        return $query->get();
    }

    /**
     * Clean up old scheduled messages
     */
    public function cleanupOldScheduledMessages(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);

        $deleted = ScheduledMessage::whereIn('status', ['sent', 'cancelled', 'failed'])
            ->where('updated_at', '<', $cutoffDate)
            ->delete();

        Log::info('Old scheduled messages cleaned up', [
            'deleted_count' => $deleted,
            'cutoff_date' => $cutoffDate->toISOString(),
        ]);

        return $deleted;
    }

    /**
     * Handle overdue messages (stuck in sending status)
     */
    public function handleOverdueMessages(int $minutesOverdue = 30): int
    {
        $overdueMessages = ScheduledMessage::overdue($minutesOverdue)->get();
        $handled = 0;

        foreach ($overdueMessages as $message) {
            if ($message->canRetry()) {
                $message->retry();
                Log::warning('Overdue message reset to scheduled', [
                    'scheduled_message_id' => $message->id,
                    'minutes_overdue' => $minutesOverdue,
                ]);
            } else {
                $message->markAsFailed('Message sending timed out');
                Log::error('Overdue message marked as failed', [
                    'scheduled_message_id' => $message->id,
                    'minutes_overdue' => $minutesOverdue,
                ]);
            }
            $handled++;
        }

        return $handled;
    }

    /**
     * Get scheduling statistics
     */
    public function getSchedulingStatistics(?User $user = null, ?string $conversationId = null): array
    {
        $query = ScheduledMessage::query();

        if ($user) {
            $query->forSender($user->id);
        }

        if ($conversationId) {
            $query->forConversation($conversationId);
        }

        $stats = [
            'total' => $query->count(),
            'scheduled' => $query->clone()->byStatus('scheduled')->count(),
            'sending' => $query->clone()->byStatus('sending')->count(),
            'sent' => $query->clone()->byStatus('sent')->count(),
            'failed' => $query->clone()->byStatus('failed')->count(),
            'cancelled' => $query->clone()->byStatus('cancelled')->count(),
            'ready_to_send' => $query->clone()->readyToSend()->count(),
            'failed_retryable' => $query->clone()->failedRetryable()->count(),
        ];

        // Add time-based statistics
        $now = now();
        $stats['due_next_hour'] = $query->clone()
            ->byStatus('scheduled')
            ->whereBetween('scheduled_for', [$now, $now->copy()->addHour()])
            ->count();

        $stats['due_next_day'] = $query->clone()
            ->byStatus('scheduled')
            ->whereBetween('scheduled_for', [$now, $now->copy()->addDay()])
            ->count();

        return $stats;
    }

    /**
     * Encrypt message content for scheduled messages
     */
    private function encryptScheduledMessage(ScheduledMessage $scheduledMessage, array $messageData): array
    {
        try {
            $conversation = $scheduledMessage->conversation;
            $encryptionResult = $this->encryption->encryptMessage(
                $messageData['content'],
                $conversation
            );

            $messageData['encrypted_content'] = $encryptionResult['encrypted_content'];
            $messageData['encryption_version'] = $encryptionResult['version'];
            $messageData['content'] = null; // Remove plain content for encrypted messages

            return $messageData;
        } catch (\Exception $e) {
            Log::error('Failed to encrypt scheduled message', [
                'scheduled_message_id' => $scheduledMessage->id,
                'conversation_id' => $scheduledMessage->conversation_id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to encrypt scheduled message: ' . $e->getMessage());
        }
    }
}