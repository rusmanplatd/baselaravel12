<?php

namespace App\Jobs;

use App\Models\Chat\Conversation;
use App\Services\MultiDeviceEncryptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RotateConversationKeysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $conversation;

    public $reason;

    public $isRecurring;

    public $intervalDays;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Conversation $conversation,
        string $reason = 'Scheduled rotation',
        bool $isRecurring = false,
        ?int $intervalDays = null
    ) {
        $this->conversation = $conversation;
        $this->reason = $reason;
        $this->isRecurring = $isRecurring;
        $this->intervalDays = $intervalDays;
    }

    /**
     * Execute the job.
     */
    public function handle(MultiDeviceEncryptionService $encryptionService): void
    {
        try {
            Log::info('Starting scheduled key rotation', [
                'conversation_id' => $this->conversation->id,
                'reason' => $this->reason,
                'is_recurring' => $this->isRecurring,
            ]);

            // Get a trusted device to perform the rotation
            $trustedDevice = $this->conversation->participants()
                ->with('user.devices')
                ->get()
                ->flatMap(fn ($participant) => $participant->user->devices)
                ->where('is_trusted', true)
                ->where('is_active', true)
                ->first();

            if (! $trustedDevice) {
                Log::error('No trusted device found for key rotation', [
                    'conversation_id' => $this->conversation->id,
                ]);
                $this->fail('No trusted device available for key rotation');

                return;
            }

            // Perform the key rotation
            $results = $encryptionService->rotateConversationKeys(
                $this->conversation,
                $trustedDevice
            );

            Log::info('Key rotation completed', [
                'conversation_id' => $this->conversation->id,
                'results' => $results,
            ]);

            // Schedule next rotation if recurring
            if ($this->isRecurring && $this->intervalDays > 0) {
                $nextRotation = now()->addDays($this->intervalDays);

                static::dispatch(
                    $this->conversation,
                    'Recurring scheduled rotation',
                    $this->isRecurring,
                    $this->intervalDays
                )->delay($nextRotation);

                Log::info('Next key rotation scheduled', [
                    'conversation_id' => $this->conversation->id,
                    'next_rotation' => $nextRotation->toDateTimeString(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Key rotation job failed', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Key rotation job failed permanently', [
            'conversation_id' => $this->conversation->id,
            'reason' => $this->reason,
            'error' => $exception->getMessage(),
        ]);

        // Optionally, you could dispatch a notification to administrators
        // or add the conversation to a failed rotation queue for manual intervention
    }
}
