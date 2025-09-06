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


    private function getOrCreateAESKey(Conversation $conversation): string
    {
        // Try to get existing key from conversation settings
        $settings = $conversation->settings ?? [];
        
        if (isset($settings['aes_key_id'])) {
            $existingKey = $this->retrieveAESKey($settings['aes_key_id']);
            if ($existingKey) {
                return $existingKey;
            }
        }
        
        // Generate new AES key
        $newKey = $this->generateSecureAESKey();
        $keyId = $this->storeAESKey($newKey, $conversation);
        
        // Update conversation settings with key ID
        $settings['aes_key_id'] = $keyId;
        $settings['key_created_at'] = now();
        $conversation->update(['settings' => $settings]);
        
        return $newKey;
    }

    private function getAESKey(Conversation $conversation): ?string
    {
        $settings = $conversation->settings ?? [];
        
        if (!isset($settings['aes_key_id'])) {
            return null;
        }
        
        return $this->retrieveAESKey($settings['aes_key_id']);
    }

    /**
     * Generate a cryptographically secure AES key
     */
    private function generateSecureAESKey(): string
    {
        // Generate 32 bytes (256 bits) of cryptographically secure random data
        return random_bytes(32);
    }

    /**
     * Store AES key securely and return key ID
     */
    private function storeAESKey(string $key, Conversation $conversation): string
    {
        $keyId = 'aes_' . bin2hex(random_bytes(16));
        
        // In production, this would be stored in a secure key management system
        // For now, we'll use Laravel's encrypted database storage
        \DB::table('conversation_encryption_keys')->insert([
            'key_id' => $keyId,
            'conversation_id' => $conversation->id,
            'key_data' => encrypt(base64_encode($key)),
            'algorithm' => 'AES-256-GCM',
            'created_at' => now(),
            'expires_at' => now()->addYear(), // Keys expire after 1 year
        ]);
        
        return $keyId;
    }

    /**
     * Retrieve AES key by key ID
     */
    private function retrieveAESKey(string $keyId): ?string
    {
        try {
            $keyRecord = \DB::table('conversation_encryption_keys')
                ->where('key_id', $keyId)
                ->where('expires_at', '>', now())
                ->first();
                
            if (!$keyRecord) {
                return null;
            }
            
            // Decrypt and decode the key
            $encryptedKey = decrypt($keyRecord->key_data);
            return base64_decode($encryptedKey);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve AES key', [
                'key_id' => $keyId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Enhanced AES key rotation with proper key lifecycle management
     */
    private function rotateAESKeys(Conversation $conversation): void
    {
        try {
            // Mark old keys as deprecated but don't delete immediately
            $settings = $conversation->settings ?? [];
            $oldKeyId = $settings['aes_key_id'] ?? null;
            
            if ($oldKeyId) {
                \DB::table('conversation_encryption_keys')
                    ->where('key_id', $oldKeyId)
                    ->update([
                        'status' => 'deprecated',
                        'deprecated_at' => now()
                    ]);
            }
            
            // Generate new key
            $newKey = $this->generateSecureAESKey();
            $newKeyId = $this->storeAESKey($newKey, $conversation);
            
            // Update conversation settings
            $settings['aes_key_id'] = $newKeyId;
            $settings['previous_key_id'] = $oldKeyId;
            $settings['last_key_rotation'] = now();
            $settings['key_rotation_count'] = ($settings['key_rotation_count'] ?? 0) + 1;
            
            $conversation->update(['settings' => $settings]);
            
            \Log::info('AES key rotation completed', [
                'conversation_id' => $conversation->id,
                'old_key_id' => $oldKeyId,
                'new_key_id' => $newKeyId,
                'rotation_count' => $settings['key_rotation_count']
            ]);
            
        } catch (\Exception $e) {
            \Log::error('AES key rotation failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Clean up expired keys (should be called periodically)
     */
    public function cleanupExpiredKeys(): int
    {
        try {
            $deletedCount = \DB::table('conversation_encryption_keys')
                ->where('expires_at', '<', now())
                ->orWhere(function ($query) {
                    $query->where('status', 'deprecated')
                          ->where('deprecated_at', '<', now()->subDays(30)); // Keep deprecated keys for 30 days
                })
                ->delete();
                
            if ($deletedCount > 0) {
                \Log::info('Cleaned up expired encryption keys', ['count' => $deletedCount]);
            }
            
            return $deletedCount;
        } catch (\Exception $e) {
            \Log::error('Failed to cleanup expired keys', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get encryption key statistics for a conversation
     */
    public function getEncryptionStats(Conversation $conversation): array
    {
        $settings = $conversation->settings ?? [];
        $keyId = $settings['aes_key_id'] ?? null;
        
        if (!$keyId) {
            return [
                'has_encryption' => false,
                'algorithm' => null,
                'key_age' => null,
                'rotation_count' => 0,
                'last_rotation' => null,
            ];
        }
        
        $keyRecord = \DB::table('conversation_encryption_keys')
            ->where('key_id', $keyId)
            ->first();
            
        return [
            'has_encryption' => true,
            'algorithm' => $keyRecord->algorithm ?? 'AES-256-GCM',
            'key_age' => $keyRecord ? now()->diffInDays($keyRecord->created_at) : null,
            'rotation_count' => $settings['key_rotation_count'] ?? 0,
            'last_rotation' => $settings['last_key_rotation'] ?? null,
            'expires_at' => $keyRecord->expires_at ?? null,
        ];
    }
}
