<?php

namespace App\Events\Chat;

use App\Models\Chat\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public string $updateType,
        public ?array $additionalData = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.'.$this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation' => [
                'id' => $this->conversation->id,
                'name' => $this->conversation->name,
                'type' => $this->conversation->type,
                'description' => $this->conversation->description,
                'status' => $this->conversation->status,
                'last_message_at' => $this->conversation->last_message_at,
                'updated_at' => $this->conversation->updated_at,
            ],
            'update_type' => $this->updateType,
            'additional_data' => $this->additionalData,
        ];
    }
}
