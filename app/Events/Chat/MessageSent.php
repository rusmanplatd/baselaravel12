<?php

namespace App\Events\Chat;

use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public User $sender,
        public string $decryptedContent
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.'.$this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'type' => $this->message->type,
                'content' => $this->decryptedContent,
                'metadata' => $this->message->metadata,
                'is_edited' => $this->message->is_edited,
                'created_at' => $this->message->created_at,
                'updated_at' => $this->message->updated_at,
            ],
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'email' => $this->sender->email,
            ],
        ];
    }
}
