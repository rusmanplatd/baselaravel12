<?php

namespace App\Services;

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\User;
use App\Models\UserDevice;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GroupEncryptionService
{
    public function __construct(
        private QuantumCryptoService $quantumService,
        private SignalProtocolService $signalService
    ) {}

    /**
     * Initialize group encryption with sender keys and group secrets
     */
    public function initializeGroupEncryption(
        Conversation $conversation,
        User $creator,
        string $encryptionMode = 'standard'
    ): void {
        try {
            // Generate master group secret
            $groupSecret = $this->generateGroupSecret();

            // Generate sender signing key for message authentication
            $senderSigningKey = $this->generateSenderSigningKey();

            // Store encrypted group metadata
            $conversation->update([
                'encryption_info' => [
                    'mode' => $encryptionMode,
                    'master_secret_encrypted' => Crypt::encryptString($groupSecret),
                    'sender_signing_key_encrypted' => Crypt::encryptString($senderSigningKey),
                    'initialized_at' => now()->toISOString(),
                    'key_version' => 1,
                ],
            ]);

            // Generate keys for all current participants
            $participants = $conversation->activeParticipants()->with('user.devices')->get();

            foreach ($participants as $participant) {
                $this->generateKeysForParticipant($conversation, $participant->user, $groupSecret, $encryptionMode);
            }

            Log::info('Group encryption initialized', [
                'conversation_id' => $conversation->id,
                'mode' => $encryptionMode,
                'participant_count' => $participants->count(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to initialize group encryption', [
                'conversation_id' => $conversation->id,
                'creator_id' => $creator->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate encryption keys for a new participant
     */
    public function generateKeysForNewParticipant(Conversation $conversation, User $user): void
    {
        $encryptionInfo = $conversation->encryption_info ?? [];
        $encryptionMode = $encryptionInfo['mode'] ?? 'standard';

        // Decrypt group secret
        $groupSecret = Crypt::decryptString($encryptionInfo['master_secret_encrypted']);

        // Generate keys for all user's devices
        $this->generateKeysForParticipant($conversation, $user, $groupSecret, $encryptionMode);

        Log::info('Keys generated for new participant', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'device_count' => $user->devices()->active()->count(),
        ]);
    }

    /**
     * Rotate group encryption keys
     */
    public function rotateGroupKeys(Conversation $conversation, User $rotatedBy): void
    {
        try {
            $encryptionInfo = $conversation->encryption_info ?? [];
            $currentVersion = $encryptionInfo['key_version'] ?? 1;
            $newVersion = $currentVersion + 1;

            // Generate new group secret and sender signing key
            $newGroupSecret = $this->generateGroupSecret();
            $newSenderSigningKey = $this->generateSenderSigningKey();

            // Update conversation encryption info
            $conversation->update([
                'encryption_info' => array_merge($encryptionInfo, [
                    'master_secret_encrypted' => Crypt::encryptString($newGroupSecret),
                    'sender_signing_key_encrypted' => Crypt::encryptString($newSenderSigningKey),
                    'key_version' => $newVersion,
                    'rotated_at' => now()->toISOString(),
                    'rotated_by' => $rotatedBy->id,
                ]),
            ]);

            // Revoke old keys
            EncryptionKey::where('conversation_id', $conversation->id)
                ->where('key_version', '<', $newVersion)
                ->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'revocation_reason' => 'Key rotation',
                ]);

            // Generate new keys for all participants
            $participants = $conversation->activeParticipants()->with('user.devices')->get();
            $encryptionMode = $encryptionInfo['mode'] ?? 'standard';

            foreach ($participants as $participant) {
                $this->generateKeysForParticipant(
                    $conversation,
                    $participant->user,
                    $newGroupSecret,
                    $encryptionMode,
                    $newVersion
                );
            }

            Log::info('Group keys rotated', [
                'conversation_id' => $conversation->id,
                'rotated_by' => $rotatedBy->id,
                'old_version' => $currentVersion,
                'new_version' => $newVersion,
                'participant_count' => $participants->count(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to rotate group keys', [
                'conversation_id' => $conversation->id,
                'rotated_by' => $rotatedBy->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Encrypt message for group using sender keys
     */
    public function encryptGroupMessage(
        string $plaintext,
        Conversation $conversation,
        User $sender,
        UserDevice $senderDevice
    ): array {
        try {
            $encryptionInfo = $conversation->encryption_info ?? [];

            if (empty($encryptionInfo)) {
                throw new Exception('Group encryption not initialized');
            }

            // Get sender's encryption key
            $senderKey = EncryptionKey::where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->where('device_id', $senderDevice->id)
                ->active()
                ->latest('key_version')
                ->first();

            if (! $senderKey) {
                throw new Exception('Sender encryption key not found');
            }

            // Generate message key from group secret
            $groupSecret = Crypt::decryptString($encryptionInfo['master_secret_encrypted']);
            $messageKey = $this->deriveMessageKey($groupSecret, $sender->id, now()->timestamp);

            // Encrypt message content
            $encryptedContent = $this->encryptWithMessageKey($plaintext, $messageKey);

            // Sign message for authenticity
            $senderSigningKey = Crypt::decryptString($encryptionInfo['sender_signing_key_encrypted']);
            $signature = $this->signMessage($encryptedContent['ciphertext'], $senderSigningKey, $sender->id);

            return [
                'encrypted_content' => json_encode([
                    'ciphertext' => $encryptedContent['ciphertext'],
                    'nonce' => $encryptedContent['nonce'],
                    'signature' => $signature,
                    'sender_id' => $sender->id,
                    'device_id' => $senderDevice->id,
                    'key_version' => $senderKey->key_version,
                    'algorithm' => $senderKey->algorithm,
                ]),
                'content_hash' => hash('sha256', $plaintext),
                'encryption_algorithm' => $senderKey->algorithm,
                'key_version' => $senderKey->key_version,
            ];

        } catch (Exception $e) {
            Log::error('Failed to encrypt group message', [
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Decrypt group message
     */
    public function decryptGroupMessage(
        string $encryptedContent,
        Conversation $conversation,
        User $recipient,
        UserDevice $recipientDevice
    ): string {
        try {
            $messageData = json_decode($encryptedContent, true);
            $encryptionInfo = $conversation->encryption_info ?? [];

            // Get recipient's encryption key for the message key version
            $recipientKey = EncryptionKey::where('conversation_id', $conversation->id)
                ->where('user_id', $recipient->id)
                ->where('device_id', $recipientDevice->id)
                ->where('key_version', $messageData['key_version'])
                ->active()
                ->first();

            if (! $recipientKey) {
                throw new Exception('Recipient encryption key not found');
            }

            // Verify message signature
            $senderSigningKey = Crypt::decryptString($encryptionInfo['sender_signing_key_encrypted']);
            if (! $this->verifyMessageSignature(
                $messageData['ciphertext'],
                $messageData['signature'],
                $senderSigningKey,
                $messageData['sender_id']
            )) {
                throw new Exception('Message signature verification failed');
            }

            // Derive message key
            $groupSecret = Crypt::decryptString($encryptionInfo['master_secret_encrypted']);
            $messageKey = $this->deriveMessageKey($groupSecret, $messageData['sender_id'], $messageData['timestamp'] ?? 0);

            // Decrypt message
            return $this->decryptWithMessageKey(
                $messageData['ciphertext'],
                $messageData['nonce'],
                $messageKey
            );

        } catch (Exception $e) {
            Log::error('Failed to decrypt group message', [
                'conversation_id' => $conversation->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle member removal - forward secrecy
     */
    public function handleMemberRemoval(Conversation $conversation, User $removedUser, string $reason): void
    {
        try {
            // Revoke all encryption keys for removed user
            EncryptionKey::where('conversation_id', $conversation->id)
                ->where('user_id', $removedUser->id)
                ->active()
                ->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'revocation_reason' => $reason,
                ]);

            // Rotate group keys for forward secrecy
            // This ensures the removed user can't decrypt future messages
            $this->rotateGroupKeys($conversation, $removedUser); // This should be called by admin who removed

            Log::info('Member removal handled with key revocation', [
                'conversation_id' => $conversation->id,
                'removed_user_id' => $removedUser->id,
                'reason' => $reason,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to handle member removal', [
                'conversation_id' => $conversation->id,
                'removed_user_id' => $removedUser->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get group encryption status
     */
    public function getGroupEncryptionStatus(Conversation $conversation): array
    {
        $encryptionInfo = $conversation->encryption_info ?? [];

        if (empty($encryptionInfo)) {
            return [
                'initialized' => false,
                'mode' => 'none',
            ];
        }

        $activeKeys = EncryptionKey::where('conversation_id', $conversation->id)
            ->active()
            ->count();

        $participantCount = $conversation->activeParticipants()->count();
        $devicesWithKeys = EncryptionKey::where('conversation_id', $conversation->id)
            ->active()
            ->distinct('user_id', 'device_id')
            ->count();

        return [
            'initialized' => true,
            'mode' => $encryptionInfo['mode'] ?? 'unknown',
            'algorithm' => $conversation->encryption_algorithm,
            'key_version' => $encryptionInfo['key_version'] ?? 1,
            'active_keys' => $activeKeys,
            'participant_count' => $participantCount,
            'devices_with_keys' => $devicesWithKeys,
            'last_rotation' => $encryptionInfo['rotated_at'] ?? $encryptionInfo['initialized_at'],
            'quantum_ready' => str_contains($conversation->encryption_algorithm, 'ML-KEM'),
        ];
    }

    /**
     * Generate encryption keys for a participant across all their devices
     */
    private function generateKeysForParticipant(
        Conversation $conversation,
        User $user,
        string $groupSecret,
        string $encryptionMode,
        int $keyVersion = 1
    ): void {
        $devices = $user->devices()->active()->get();

        foreach ($devices as $device) {
            $this->generateDeviceKey($conversation, $user, $device, $groupSecret, $encryptionMode, $keyVersion);
        }
    }

    /**
     * Generate encryption key for specific device
     */
    private function generateDeviceKey(
        Conversation $conversation,
        User $user,
        UserDevice $device,
        string $groupSecret,
        string $encryptionMode,
        int $keyVersion = 1
    ): void {
        // Derive device-specific key from group secret
        $deviceKey = $this->deriveDeviceKey($groupSecret, $user->id, $device->id);

        // Generate key pair based on encryption mode
        if ($encryptionMode === 'quantum' || $encryptionMode === 'hybrid') {
            $algorithm = $device->quantum_ready ? 'ML-KEM-768' : 'AES-256-GCM';
            if (str_contains($algorithm, 'ML-KEM')) {
                $keyPair = $this->quantumService->generateKeyPair($algorithm);
            } else {
                $keyPair = $this->generateClassicalKeyPair();
            }
        } else {
            $keyPair = $this->generateClassicalKeyPair();
            $algorithm = 'AES-256-GCM';
        }

        // Store encryption key
        EncryptionKey::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'encrypted_key' => Crypt::encryptString($keyPair['private']),
            'public_key' => base64_encode($keyPair['public']),
            'device_fingerprint' => $device->device_fingerprint,
            'algorithm' => $algorithm,
            'key_version' => $keyVersion,
            'device_metadata' => $device->device_info,
        ]);
    }

    /**
     * Generate master group secret (32 bytes)
     */
    private function generateGroupSecret(): string
    {
        return random_bytes(32);
    }

    /**
     * Generate sender signing key for message authentication
     */
    private function generateSenderSigningKey(): string
    {
        return random_bytes(32);
    }

    /**
     * Derive message-specific key from group secret
     */
    private function deriveMessageKey(string $groupSecret, string $senderId, int $timestamp): string
    {
        $info = "message_key_{$senderId}_{$timestamp}";

        return hash_hkdf('sha256', $groupSecret, 32, $info);
    }

    /**
     * Derive device-specific key from group secret
     */
    private function deriveDeviceKey(string $groupSecret, string $userId, string $deviceId): string
    {
        $info = "device_key_{$userId}_{$deviceId}";

        return hash_hkdf('sha256', $groupSecret, 32, $info);
    }

    /**
     * Encrypt with derived message key
     */
    private function encryptWithMessageKey(string $plaintext, string $key): array
    {
        $nonce = random_bytes(12); // For AES-GCM
        $ciphertext = sodium_crypto_aead_aes256gcm_encrypt($plaintext, '', $nonce, $key);

        return [
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
        ];
    }

    /**
     * Decrypt with derived message key
     */
    private function decryptWithMessageKey(string $ciphertext, string $nonce, string $key): string
    {
        $decrypted = sodium_crypto_aead_aes256gcm_decrypt(
            base64_decode($ciphertext),
            '',
            base64_decode($nonce),
            $key
        );

        if ($decrypted === false) {
            throw new Exception('Group message decryption failed');
        }

        return $decrypted;
    }

    /**
     * Sign message for authenticity
     */
    private function signMessage(string $ciphertext, string $signingKey, string $senderId): string
    {
        $dataToSign = $ciphertext.$senderId;

        return base64_encode(hash_hmac('sha256', $dataToSign, $signingKey, true));
    }

    /**
     * Verify message signature
     */
    private function verifyMessageSignature(
        string $ciphertext,
        string $signature,
        string $signingKey,
        string $senderId
    ): bool {
        $dataToSign = $ciphertext.$senderId;
        $expectedSignature = base64_encode(hash_hmac('sha256', $dataToSign, $signingKey, true));

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate classical key pair
     */
    private function generateClassicalKeyPair(): array
    {
        $privateKey = random_bytes(32);
        $publicKey = hash('sha256', $privateKey);

        return [
            'private' => $privateKey,
            'public' => $publicKey,
        ];
    }
}
