<?php

namespace App\Services;

use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\User;

class ChatEncryptionService
{
    public function __construct(
        private QuantumCryptoService $quantumCryptoService,
        private GroupEncryptionService $groupEncryptionService
    ) {}

    public function encryptMessage(string $content, Conversation $conversation, User $user): array
    {
        $algorithm = $conversation->settings['encryption_algorithm'] ?? 'AES-256-GCM';
        
        return match($algorithm) {
            'ML-KEM-768', 'ML-KEM-512', 'ML-KEM-1024' => $this->encryptWithQuantum($content, $conversation, $user),
            'signal' => $this->groupEncryptionService->encryptMessage($content, $conversation, $user),
            default => $this->encryptWithAES($content, $conversation, $user),
        };
    }

    public function decryptMessage(Message $message, User $user): ?string
    {
        if (empty($message->encrypted_content)) {
            return null;
        }

        $conversation = $message->conversation;
        $algorithm = $conversation->settings['encryption_algorithm'] ?? 'AES-256-GCM';

        try {
            return match($algorithm) {
                'ML-KEM-768', 'ML-KEM-512', 'ML-KEM-1024' => $this->decryptWithQuantum($message, $user),
                'signal' => $this->groupEncryptionService->decryptMessage($message, $user),
                default => $this->decryptWithAES($message, $user),
            };
        } catch (\Exception $e) {
            \Log::error('Message decryption failed', [
                'message_id' => $message->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function rotateConversationKeys(Conversation $conversation): void
    {
        $algorithm = $conversation->settings['encryption_algorithm'] ?? 'AES-256-GCM';

        match($algorithm) {
            'ML-KEM-768', 'ML-KEM-512', 'ML-KEM-1024' => $this->rotateQuantumKeys($conversation),
            'signal' => $this->groupEncryptionService->rotateConversationKeys($conversation),
            default => $this->rotateAESKeys($conversation),
        };
    }

    public function isQuantumReady(Conversation $conversation): bool
    {
        return $this->quantumCryptoService->isConversationQuantumReady($conversation);
    }

    private function encryptWithQuantum(string $content, Conversation $conversation, User $user): array
    {
        return $this->quantumCryptoService->encryptForConversation($content, $conversation, $user);
    }

    private function decryptWithQuantum(Message $message, User $user): string
    {
        return $this->quantumCryptoService->decryptMessage($message, $user);
    }

    private function rotateQuantumKeys(Conversation $conversation): void
    {
        $this->quantumCryptoService->rotateConversationKeys($conversation);
    }

    private function encryptWithAES(string $content, Conversation $conversation, User $user): array
    {
        $key = $this->getOrCreateAESKey($conversation);
        $iv = random_bytes(12);
        
        $encrypted = openssl_encrypt($content, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        return [
            'encrypted_content' => base64_encode($encrypted),
            'content_hash' => hash('sha256', $content),
            'content_hmac' => hash_hmac('sha256', $encrypted, $key),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
        ];
    }

    private function decryptWithAES(Message $message, User $user): string
    {
        $key = $this->getAESKey($message->conversation);
        if (!$key) {
            throw new \Exception('Encryption key not found');
        }

        $encryptedData = base64_decode($message->encrypted_content);
        $iv = base64_decode($message->metadata['iv'] ?? '');
        $tag = base64_decode($message->metadata['tag'] ?? '');

        $decrypted = openssl_decrypt($encryptedData, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }

        return $decrypted;
    }

    private function rotateAESKeys(Conversation $conversation): void
    {
        // AES key rotation logic would be implemented here
        // For now, just update the settings to indicate key rotation occurred
        $settings = $conversation->settings ?? [];
        $settings['last_key_rotation'] = now();
        $conversation->update(['settings' => $settings]);
    }

    private function getOrCreateAESKey(Conversation $conversation): string
    {
        // In a real implementation, this would retrieve or create an AES key
        // For testing purposes, return a mock key
        return hash('sha256', 'mock_aes_key_' . $conversation->id);
    }

    private function getAESKey(Conversation $conversation): ?string
    {
        // In a real implementation, this would retrieve the AES key
        // For testing purposes, return a mock key
        return hash('sha256', 'mock_aes_key_' . $conversation->id);
    }
}