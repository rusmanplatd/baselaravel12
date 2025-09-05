<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Chat\BotConversation;
use App\Models\Chat\BotMessage;
use App\Models\Chat\BotEncryptionKey;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotService
{
    public function __construct(
        private QuantumCryptoService $quantumCrypto,
        private ChatEncryptionService $encryption
    ) {}

    /**
     * Add bot to conversation
     */
    public function addBotToConversation(Bot $bot, Conversation $conversation, array $permissions = []): BotConversation
    {
        $botConversation = BotConversation::create([
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'status' => 'active',
            'permissions' => array_merge([
                'read_messages',
                'send_messages',
            ], $permissions),
            'context' => [
                'added_at' => now()->toISOString(),
                'conversation_type' => $conversation->type,
            ],
        ]);

        // Initialize encryption keys if bot supports quantum E2EE
        if ($bot->isQuantumCapable()) {
            $this->initializeBotEncryption($bot, $conversation);
        }

        // Notify bot about being added to conversation
        $this->notifyBot($bot, 'conversation.added', [
            'conversation_id' => $conversation->id,
            'conversation_name' => $conversation->name,
            'participant_count' => $conversation->participants()->count(),
        ]);

        return $botConversation;
    }

    /**
     * Remove bot from conversation
     */
    public function removeBotFromConversation(Bot $bot, Conversation $conversation): void
    {
        $botConversation = BotConversation::where('bot_id', $bot->id)
            ->where('conversation_id', $conversation->id)
            ->first();

        if ($botConversation) {
            $botConversation->remove();

            // Deactivate encryption keys
            BotEncryptionKey::where('bot_id', $bot->id)
                ->where('conversation_id', $conversation->id)
                ->update(['is_active' => false]);

            // Notify bot about removal
            $this->notifyBot($bot, 'conversation.removed', [
                'conversation_id' => $conversation->id,
            ]);
        }
    }

    /**
     * Process incoming message for bot
     */
    public function processIncomingMessage(Bot $bot, Message $message): void
    {
        $conversation = $message->conversation;
        
        $botConversation = BotConversation::where('bot_id', $bot->id)
            ->where('conversation_id', $conversation->id)
            ->where('status', 'active')
            ->first();

        if (!$botConversation || !$botConversation->hasPermission('read_messages')) {
            return;
        }

        // Decrypt message if bot supports E2EE
        $content = $message->content;
        $encryptedContent = null;

        if ($bot->isQuantumCapable() && $message->isEncrypted()) {
            try {
                $content = $this->decryptMessageForBot($bot, $message);
                $encryptedContent = $message->encrypted_content;
            } catch (\Exception $e) {
                Log::warning('Failed to decrypt message for bot', [
                    'bot_id' => $bot->id,
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
                
                // Skip processing if decryption fails
                return;
            }
        }

        // Create bot message record
        $botMessage = BotMessage::create([
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'bot_conversation_id' => $botConversation->id,
            'message_id' => $message->id,
            'direction' => 'incoming',
            'content' => $content,
            'encrypted_content' => $encryptedContent,
            'encryption_version' => $message->encryption_version,
            'content_type' => $message->content_type ?? 'text',
            'metadata' => [
                'sender_id' => $message->sender_id,
                'timestamp' => $message->created_at->toISOString(),
            ],
        ]);

        // Send to bot webhook
        $this->sendMessageToBot($bot, $botMessage);

        $botMessage->markAsProcessed();
        $botConversation->updateLastMessageTime();
    }

    /**
     * Send message from bot to conversation
     */
    public function sendBotMessage(Bot $bot, Conversation $conversation, array $data): ?Message
    {
        $botConversation = BotConversation::where('bot_id', $bot->id)
            ->where('conversation_id', $conversation->id)
            ->where('status', 'active')
            ->first();

        if (!$botConversation || !$botConversation->hasPermission('send_messages')) {
            return null;
        }

        // Create message
        $messageData = [
            'conversation_id' => $conversation->id,
            'sender_id' => null, // Bot messages don't have a sender_id
            'sender_type' => 'bot',
            'sender_bot_id' => $bot->id,
            'content' => $data['content'],
            'content_type' => $data['content_type'] ?? 'text',
            'metadata' => array_merge($data['metadata'] ?? [], [
                'bot_id' => $bot->id,
                'bot_name' => $bot->name,
            ]),
        ];

        // Encrypt message if bot supports E2EE and conversation is encrypted
        if ($bot->isQuantumCapable() && $conversation->isEncrypted()) {
            try {
                $encryptionResult = $this->encryptBotMessage($bot, $conversation, $messageData['content']);
                $messageData['encrypted_content'] = $encryptionResult['encrypted_content'];
                $messageData['encryption_version'] = $encryptionResult['version'];
                $messageData['content'] = null; // Remove plain content for encrypted messages
            } catch (\Exception $e) {
                Log::error('Failed to encrypt bot message', [
                    'bot_id' => $bot->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        $message = Message::create($messageData);

        // Create bot message record
        $botMessage = BotMessage::create([
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'bot_conversation_id' => $botConversation->id,
            'message_id' => $message->id,
            'direction' => 'outgoing',
            'content' => $data['content'],
            'encrypted_content' => $messageData['encrypted_content'] ?? null,
            'encryption_version' => $messageData['encryption_version'] ?? 1,
            'content_type' => $data['content_type'] ?? 'text',
            'metadata' => $data['metadata'] ?? [],
        ]);

        $botMessage->markResponseSent();
        $botConversation->updateLastMessageTime();

        return $message;
    }

    /**
     * Initialize bot encryption for conversation
     */
    private function initializeBotEncryption(Bot $bot, Conversation $conversation): void
    {
        // Generate quantum key pair for bot
        try {
            $algorithm = $this->selectBestAlgorithmForBot($bot);
            $keyPair = $this->quantumCrypto->generateKeyPair($algorithm);

            BotEncryptionKey::create([
                'bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'key_type' => 'primary',
                'algorithm' => $algorithm,
                'public_key' => base64_encode($keyPair['public']),
                'encrypted_private_key' => $this->encryptBotPrivateKey($keyPair['private'], $bot),
                'key_pair_id' => uniqid('bot_key_'),
                'version' => 3, // Quantum version
                'is_active' => true,
                'expires_at' => now()->addDays(30),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to initialize bot encryption', [
                'bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            // Fall back to RSA if quantum fails
            $this->initializeBotRSAEncryption($bot, $conversation);
        }
    }

    /**
     * Initialize RSA encryption for bot (fallback)
     */
    private function initializeBotRSAEncryption(Bot $bot, Conversation $conversation): void
    {
        $keyPair = $this->encryption->generateRSAKeyPair(4096);

        BotEncryptionKey::create([
            'bot_id' => $bot->id,
            'conversation_id' => $conversation->id,
            'key_type' => 'primary',
            'algorithm' => 'RSA-4096-OAEP',
            'public_key' => $keyPair['public'],
            'encrypted_private_key' => $this->encryptBotPrivateKey($keyPair['private'], $bot),
            'key_pair_id' => uniqid('bot_rsa_'),
            'version' => 2, // RSA version
            'is_active' => true,
            'expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Decrypt message for bot
     */
    private function decryptMessageForBot(Bot $bot, Message $message): string
    {
        $encryptionKey = BotEncryptionKey::where('bot_id', $bot->id)
            ->where('conversation_id', $message->conversation_id)
            ->active()
            ->first();

        if (!$encryptionKey) {
            throw new \Exception('No active encryption key found for bot');
        }

        $privateKey = $this->decryptBotPrivateKey($encryptionKey->encrypted_private_key, $bot);

        if ($encryptionKey->isQuantum()) {
            return $this->quantumCrypto->decrypt($message->encrypted_content, $privateKey);
        } else {
            return $this->encryption->decrypt($message->encrypted_content, $privateKey);
        }
    }

    /**
     * Encrypt message from bot
     */
    private function encryptBotMessage(Bot $bot, Conversation $conversation, string $content): array
    {
        $encryptionKey = BotEncryptionKey::where('bot_id', $bot->id)
            ->where('conversation_id', $conversation->id)
            ->active()
            ->first();

        if (!$encryptionKey) {
            throw new \Exception('No active encryption key found for bot');
        }

        $publicKeys = $this->getConversationPublicKeys($conversation);
        
        if ($encryptionKey->isQuantum()) {
            $encrypted = $this->quantumCrypto->encryptForMultipleRecipients($content, $publicKeys);
            return [
                'encrypted_content' => $encrypted,
                'version' => 3,
            ];
        } else {
            $encrypted = $this->encryption->encryptForMultipleRecipients($content, $publicKeys);
            return [
                'encrypted_content' => $encrypted,
                'version' => 2,
            ];
        }
    }

    /**
     * Send message to bot webhook
     */
    private function sendMessageToBot(Bot $bot, BotMessage $botMessage): void
    {
        if (!$bot->webhook_url) {
            return;
        }

        $payload = [
            'event' => 'message.received',
            'timestamp' => now()->toISOString(),
            'data' => [
                'message_id' => $botMessage->message_id,
                'conversation_id' => $botMessage->conversation_id,
                'content' => $botMessage->getContent(),
                'content_type' => $botMessage->getContentType(),
                'sender_id' => $botMessage->getMetadata('sender_id'),
                'timestamp' => $botMessage->getMetadata('timestamp'),
                'is_encrypted' => $botMessage->isEncrypted(),
                'encryption_version' => $botMessage->getEncryptionVersion(),
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => $bot->getUserAgent(),
            'X-Bot-Signature' => $this->generateBotSignature($bot, $payload),
            'X-Bot-ID' => $bot->id,
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($bot->webhook_url, $payload);

            Log::info('Bot webhook sent', [
                'bot_id' => $bot->id,
                'message_id' => $botMessage->id,
                'status_code' => $response->status(),
            ]);

        } catch (\Exception $e) {
            Log::error('Bot webhook failed', [
                'bot_id' => $bot->id,
                'message_id' => $botMessage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify bot about events
     */
    private function notifyBot(Bot $bot, string $event, array $data): void
    {
        if (!$bot->webhook_url) {
            return;
        }

        $payload = [
            'event' => $event,
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => $bot->getUserAgent(),
            'X-Bot-Signature' => $this->generateBotSignature($bot, $payload),
            'X-Bot-ID' => $bot->id,
        ];

        try {
            Http::timeout(30)
                ->withHeaders($headers)
                ->post($bot->webhook_url, $payload);

        } catch (\Exception $e) {
            Log::warning('Bot notification failed', [
                'bot_id' => $bot->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate HMAC signature for bot webhook
     */
    private function generateBotSignature(Bot $bot, array $payload): string
    {
        if (!$bot->webhook_secret) {
            return '';
        }

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $bot->webhook_secret);
        
        return 'sha256=' . $signature;
    }

    /**
     * Validate bot webhook signature
     */
    public function validateBotSignature(Bot $bot, string $payload, string $signature): bool
    {
        if (!$bot->webhook_secret) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $bot->webhook_secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Encrypt bot private key
     */
    private function encryptBotPrivateKey(string $privateKey, Bot $bot): string
    {
        // Use bot's API token as encryption key
        $key = hash('sha256', $bot->api_token, true);
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt($privateKey, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt bot private key
     */
    private function decryptBotPrivateKey(string $encryptedPrivateKey, Bot $bot): string
    {
        $data = base64_decode($encryptedPrivateKey);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $key = hash('sha256', $bot->api_token, true);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt bot private key');
        }
        
        return $decrypted;
    }

    /**
     * Select best encryption algorithm for bot
     */
    private function selectBestAlgorithmForBot(Bot $bot): string
    {
        $config = $bot->getConfiguration('encryption', []);
        
        if (isset($config['preferred_algorithm'])) {
            return $config['preferred_algorithm'];
        }
        
        // Default to ML-KEM-768 for quantum-capable bots
        return 'ML-KEM-768';
    }

    /**
     * Get public keys for all conversation participants
     */
    private function getConversationPublicKeys(Conversation $conversation): array
    {
        // This would fetch public keys from all participants
        // Implementation depends on existing encryption key management
        return [];
    }
}