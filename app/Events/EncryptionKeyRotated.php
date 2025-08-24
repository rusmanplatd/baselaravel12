<?php

namespace App\Events;

use App\Models\Chat\Conversation;
use App\Models\UserDevice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EncryptionKeyRotated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public UserDevice $initiatingDevice,
        public int $newKeyVersion,
        public array $rotationResults
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("conversation.{$this->conversation->id}.encryption"),
        ];

        // Add user channels for all participants
        foreach ($this->conversation->activeParticipants as $participant) {
            $channels[] = new PrivateChannel("user.{$participant->user_id}.encryption");
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'initiated_by_device' => $this->initiatingDevice->id,
            'initiated_by_device_name' => $this->initiatingDevice->display_name,
            'new_key_version' => $this->newKeyVersion,
            'timestamp' => now()->toISOString(),
            'devices_updated' => count($this->rotationResults['rotated_devices']),
            'devices_failed' => count($this->rotationResults['failed_devices']),
            'requires_sync' => count($this->rotationResults['failed_devices']) === 0,
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'encryption.key.rotated';
    }
}
