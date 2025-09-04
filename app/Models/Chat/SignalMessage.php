<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalMessage extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'message_id',
        'conversation_id',
        'session_id',
        'sender_user_id',
        'recipient_user_id',
        'message_type',
        'protocol_version',
        'registration_id',
        'prekey_id',
        'signed_prekey_id',
        'base_key',
        'identity_key',
        'ratchet_message',
        'delivery_options',
        'delivery_status',
        'sent_at',
        'delivered_at',
        'quantum_ciphertext',
        'quantum_algorithm',
        'is_quantum_resistant',
        'quantum_version',
        'quantum_key_id',
    ];

    protected $casts = [
        'protocol_version' => 'integer',
        'registration_id' => 'integer',
        'prekey_id' => 'integer',
        'signed_prekey_id' => 'integer',
        'ratchet_message' => 'array',
        'delivery_options' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'is_quantum_resistant' => 'boolean',
        'quantum_version' => 'integer',
    ];

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the session this message belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SignalSession::class);
    }

    /**
     * Get the sender of the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * Get the recipient of the message.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /**
     * Mark message as delivered.
     */
    public function markDelivered(): bool
    {
        return $this->update([
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Check if message is a prekey message.
     */
    public function isPreKeyMessage(): bool
    {
        return $this->message_type === 'prekey';
    }

    /**
     * Check if message uses quantum-resistant encryption.
     */
    public function isQuantumResistant(): bool
    {
        return $this->is_quantum_resistant === true;
    }

    /**
     * Get quantum algorithm used for this message.
     */
    public function getQuantumAlgorithm(): ?string
    {
        return $this->quantum_algorithm;
    }

    /**
     * Get quantum version of the encryption.
     */
    public function getQuantumVersion(): ?int
    {
        return $this->quantum_version;
    }

    /**
     * Get message age in minutes.
     */
    public function getAgeInMinutes(): int
    {
        return $this->sent_at->diffInMinutes(now());
    }

    /**
     * Get undelivered messages for a user.
     */
    public static function getUndeliveredForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('recipient_user_id', $userId)
            ->where('delivery_status', '!=', 'delivered')
            ->orderBy('sent_at')
            ->get();
    }

    /**
     * Get message statistics for a conversation.
     */
    public static function getConversationStats(int $conversationId): array
    {
        $messages = self::where('conversation_id', $conversationId);

        return [
            'total_messages' => $messages->count(),
            'prekey_messages' => $messages->where('message_type', 'prekey')->count(),
            'pending_delivery' => $messages->where('delivery_status', 'pending')->count(),
            'failed_delivery' => $messages->where('delivery_status', 'failed')->count(),
            'average_delivery_time' => $messages->whereNotNull('delivered_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, sent_at, delivered_at)) as avg_seconds')
                ->value('avg_seconds'),
        ];
    }
}
