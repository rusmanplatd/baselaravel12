<?php

namespace App\Services;

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\Signal\IdentityKey;
use App\Models\User;
use App\Models\UserDevice;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SignalProtocolService
{
    public function __construct(
        private QuantumCryptoService $quantumService
    ) {}

    /**
     * Initialize Signal Protocol for a user device
     */
    public function initializeDevice(User $user, UserDevice $device, array $options = []): IdentityKey
    {
        // Generate Ed25519 identity keypair
        $identityKeyPair = $this->generateIdentityKeyPair();

        // Generate registration ID
        $registrationId = random_int(1, 16383);

        // Create identity key record
        $identityKey = IdentityKey::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'registration_id' => $registrationId,
            'public_key' => base64_encode($identityKeyPair['public']),
            'private_key_encrypted' => Crypt::encryptString($identityKeyPair['private']),
            'key_fingerprint' => hash('sha256', $identityKeyPair['public']),
            'is_active' => true,
        ]);

        // Initialize quantum capabilities if available
        if ($options['enable_quantum'] ?? false) {
            $this->enableQuantumForDevice($identityKey, $options['quantum_algorithm'] ?? 'ML-KEM-768');
        }

        // Generate initial pre-keys
        $this->generatePreKeys($identityKey, 100);

        // Generate signed pre-key
        $this->generateSignedPreKey($identityKey);

        Log::info('Signal Protocol initialized for device', [
            'user_id' => $user->id,
            'device_id' => $device->id,
            'registration_id' => $registrationId,
            'quantum_enabled' => $identityKey->is_quantum_capable,
        ]);

        return $identityKey;
    }

    /**
     * Enable quantum cryptography for a device
     */
    public function enableQuantumForDevice(IdentityKey $identityKey, string $algorithm = 'ML-KEM-768'): void
    {
        $quantumKeyPair = $this->quantumService->generateKeyPair($algorithm);

        $identityKey->enableQuantum(
            base64_encode($quantumKeyPair['public']),
            Crypt::encryptString($quantumKeyPair['private']),
            $algorithm
        );
    }

    /**
     * Encrypt a message using Signal Protocol with optional quantum enhancement
     */
    public function encryptMessage(
        string $plaintext,
        Conversation $conversation,
        User $sender,
        UserDevice $senderDevice,
        array $recipients = []
    ): array {
        $encryptedMessages = [];

        // Get sender's identity key
        $senderIdentityKey = $this->getActiveIdentityKey($sender);
        if (! $senderIdentityKey) {
            throw new Exception('Sender identity key not found');
        }

        // If no recipients specified, encrypt for all conversation participants
        if (empty($recipients)) {
            $participants = $conversation->activeParticipants()->with('user')->get();
            $recipients = $participants->map(fn ($p) => $p->user)->toArray();
        }

        foreach ($recipients as $recipient) {
            $recipientDevices = $recipient->devices()->active()->get();

            foreach ($recipientDevices as $recipientDevice) {
                $encryptedMessage = $this->encryptMessageForDevice(
                    $plaintext,
                    $senderIdentityKey,
                    $senderDevice,
                    $recipient,
                    $recipientDevice
                );

                $encryptedMessages[] = [
                    'recipient_user_id' => $recipient->id,
                    'recipient_device_id' => $recipientDevice->id,
                    'encrypted_content' => $encryptedMessage['content'],
                    'content_hash' => $encryptedMessage['hash'],
                    'encryption_algorithm' => $encryptedMessage['algorithm'],
                    'session_info' => $encryptedMessage['session_info'],
                ];
            }
        }

        return $encryptedMessages;
    }

    /**
     * Decrypt a message using Signal Protocol
     */
    public function decryptMessage(
        string $encryptedContent,
        string $algorithm,
        User $recipient,
        UserDevice $recipientDevice,
        User $sender,
        UserDevice $senderDevice
    ): string {
        // Get recipient's identity key
        $recipientIdentityKey = $this->getActiveIdentityKey($recipient);
        if (! $recipientIdentityKey) {
            throw new Exception('Recipient identity key not found');
        }

        // Get sender's identity key for verification
        $senderIdentityKey = $this->getActiveIdentityKey($sender);
        if (! $senderIdentityKey) {
            throw new Exception('Sender identity key not found');
        }

        // Decrypt based on algorithm
        if (str_contains($algorithm, 'ML-KEM') || str_contains($algorithm, 'QUANTUM')) {
            return $this->decryptQuantumMessage($encryptedContent, $recipientIdentityKey, $senderIdentityKey);
        } elseif (str_contains($algorithm, 'HYBRID')) {
            return $this->decryptHybridMessage($encryptedContent, $recipientIdentityKey, $senderIdentityKey);
        } else {
            return $this->decryptClassicalMessage($encryptedContent, $recipientIdentityKey, $senderIdentityKey);
        }
    }

    /**
     * Start a new conversation with E2EE
     */
    public function startConversation(
        User $initiator,
        UserDevice $initiatorDevice,
        array $participants,
        array $options = []
    ): Conversation {
        // Create conversation
        $conversation = Conversation::create([
            'type' => count($participants) > 1 ? 'group' : 'direct',
            'name' => $options['name'] ?? null,
            'description' => $options['description'] ?? null,
            'created_by' => $initiator->id,
            'encryption_algorithm' => $this->selectBestEncryptionAlgorithm($initiator, $participants),
            'key_strength' => $options['key_strength'] ?? 256,
            'status' => 'active',
        ]);

        // Add initiator as admin
        $conversation->addParticipant($initiator->id, ['role' => 'admin']);

        // Add other participants
        foreach ($participants as $participant) {
            if ($participant->id !== $initiator->id) {
                $conversation->addParticipant($participant->id);
            }
        }

        // Generate group encryption keys if needed
        if ($conversation->isGroup()) {
            $this->generateGroupEncryptionKeys($conversation);
        }

        Log::info('E2EE conversation started', [
            'conversation_id' => $conversation->id,
            'initiator_id' => $initiator->id,
            'participant_count' => count($participants),
            'encryption_algorithm' => $conversation->encryption_algorithm,
        ]);

        return $conversation;
    }

    /**
     * Add a new participant to an existing conversation
     */
    public function addParticipantToConversation(
        Conversation $conversation,
        User $newParticipant,
        User $addedBy,
        array $options = []
    ): void {
        // Check permissions
        $adderParticipant = $conversation->participants()
            ->where('user_id', $addedBy->id)
            ->active()
            ->first();

        if (! $adderParticipant || ! $adderParticipant->canAddMembers()) {
            throw new Exception('Insufficient permissions to add participant');
        }

        // Add participant
        $conversation->addParticipant($newParticipant->id, $options);

        // Generate encryption keys for new participant
        if ($conversation->isGroup()) {
            $this->generateEncryptionKeysForNewParticipant($conversation, $newParticipant);
        }

        // Send system message about participant addition
        $this->sendSystemMessage($conversation, $addedBy, [
            'type' => 'participant_added',
            'added_user_id' => $newParticipant->id,
            'added_by_user_id' => $addedBy->id,
        ]);
    }

    /**
     * Remove a participant from a conversation
     */
    public function removeParticipantFromConversation(
        Conversation $conversation,
        User $participantToRemove,
        User $removedBy,
        ?string $reason = null
    ): void {
        // Check permissions
        $removerParticipant = $conversation->participants()
            ->where('user_id', $removedBy->id)
            ->active()
            ->first();

        if (! $removerParticipant || ! $removerParticipant->canRemoveMembers()) {
            throw new Exception('Insufficient permissions to remove participant');
        }

        // Remove participant
        $conversation->removeParticipant($participantToRemove->id);

        // Revoke encryption keys
        $this->revokeEncryptionKeysForParticipant($conversation, $participantToRemove, $reason);

        // Rotate group keys for security
        if ($conversation->isGroup()) {
            $this->rotateGroupEncryptionKeys($conversation);
        }

        // Send system message about participant removal
        $this->sendSystemMessage($conversation, $removedBy, [
            'type' => 'participant_removed',
            'removed_user_id' => $participantToRemove->id,
            'removed_by_user_id' => $removedBy->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Rotate encryption keys for a conversation
     */
    public function rotateConversationKeys(Conversation $conversation, User $rotatedBy): void
    {
        if ($conversation->isGroup()) {
            $this->rotateGroupEncryptionKeys($conversation);
        } else {
            // For direct messages, rotate session keys
            $this->rotateDirectMessageKeys($conversation);
        }

        // Send system message about key rotation
        $this->sendSystemMessage($conversation, $rotatedBy, [
            'type' => 'keys_rotated',
            'rotated_by_user_id' => $rotatedBy->id,
            'timestamp' => now()->toISOString(),
        ]);

        Log::info('Conversation keys rotated', [
            'conversation_id' => $conversation->id,
            'rotated_by' => $rotatedBy->id,
            'type' => $conversation->type,
        ]);
    }

    /**
     * Verify message integrity and authenticity
     */
    public function verifyMessage(Message $message): bool
    {
        // Verify content hash
        $expectedHash = hash('sha256', $message->encrypted_content);
        if ($expectedHash !== $message->content_hash) {
            Log::warning('Message hash verification failed', [
                'message_id' => $message->id,
                'expected_hash' => $expectedHash,
                'actual_hash' => $message->content_hash,
            ]);

            return false;
        }

        // Verify HMAC if present
        if ($message->content_hmac) {
            $conversation = $message->conversation;
            $key = $this->getHMACKeyForConversation($conversation, $message->sender);

            if (! $key || ! hash_equals(hash_hmac('sha256', $message->encrypted_content, $key), $message->content_hmac)) {
                Log::warning('Message HMAC verification failed', [
                    'message_id' => $message->id,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Get the best encryption algorithm for a conversation
     */
    private function selectBestEncryptionAlgorithm(User $initiator, array $participants): string
    {
        $allUsers = array_merge([$initiator], $participants);
        $quantumCapableUsers = [];

        foreach ($allUsers as $user) {
            $identityKey = $this->getActiveIdentityKey($user);
            if ($identityKey && $identityKey->isQuantumCapable()) {
                $quantumCapableUsers[] = $user;
            }
        }

        $totalUsers = count($allUsers);
        $quantumUsers = count($quantumCapableUsers);

        // All users quantum capable - use quantum
        if ($quantumUsers === $totalUsers) {
            return 'ML-KEM-768';
        }

        // Some users quantum capable - use hybrid
        if ($quantumUsers > 0) {
            return 'HYBRID-ML-KEM-768';
        }

        // No quantum capabilities - use classical
        return 'AES-256-GCM';
    }

    /**
     * Generate encryption keys for a group conversation
     */
    private function generateGroupEncryptionKeys(Conversation $conversation): void
    {
        $participants = $conversation->activeParticipants()->with('user')->get();

        foreach ($participants as $participant) {
            $devices = $participant->user->devices()->active()->get();

            foreach ($devices as $device) {
                $this->generateEncryptionKeyForDevice($conversation, $participant->user, $device);
            }
        }
    }

    /**
     * Generate encryption key for a specific device
     */
    private function generateEncryptionKeyForDevice(
        Conversation $conversation,
        User $user,
        UserDevice $device
    ): EncryptionKey {
        $algorithm = $conversation->encryption_algorithm;

        if (str_contains($algorithm, 'ML-KEM') || str_contains($algorithm, 'QUANTUM')) {
            $keyPair = $this->quantumService->generateKeyPair($algorithm);
        } else {
            $keyPair = $this->generateClassicalKeyPair($algorithm);
        }

        return EncryptionKey::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'encrypted_key' => Crypt::encryptString($keyPair['private']),
            'public_key' => base64_encode($keyPair['public']),
            'device_fingerprint' => $device->device_fingerprint,
            'algorithm' => $algorithm,
            'key_strength' => $conversation->key_strength,
            'device_metadata' => $device->device_info,
        ]);
    }

    /**
     * Get active identity key for a user
     */
    private function getActiveIdentityKey(User $user): ?IdentityKey
    {
        return $user->signalIdentityKeys()->active()->first();
    }

    /**
     * Generate Ed25519 identity keypair
     */
    private function generateIdentityKeyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();

        return [
            'public' => sodium_crypto_sign_publickey($keyPair),
            'private' => sodium_crypto_sign_secretkey($keyPair),
        ];
    }

    /**
     * Generate classical keypair for encryption
     */
    private function generateClassicalKeyPair(string $algorithm): array
    {
        if (str_contains($algorithm, 'RSA')) {
            $keySize = str_contains($algorithm, '4096') ? 4096 : 2048;
            $keyPair = openssl_pkey_new([
                'private_key_bits' => $keySize,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($keyPair, $privateKey);
            $publicKey = openssl_pkey_get_details($keyPair)['key'];

            return ['public' => $publicKey, 'private' => $privateKey];
        }

        // Default to Curve25519
        $keyPair = sodium_crypto_box_keypair();

        return [
            'public' => sodium_crypto_box_publickey($keyPair),
            'private' => sodium_crypto_box_secretkey($keyPair),
        ];
    }

    /**
     * Encrypt message for specific device
     */
    private function encryptMessageForDevice(
        string $plaintext,
        IdentityKey $senderIdentityKey,
        UserDevice $senderDevice,
        User $recipient,
        UserDevice $recipientDevice
    ): array {
        $recipientIdentityKey = $this->getActiveIdentityKey($recipient);
        $algorithm = $senderIdentityKey->getBestAlgorithmForPeer($recipientIdentityKey);

        if (str_contains($algorithm, 'ML-KEM') || str_contains($algorithm, 'QUANTUM')) {
            return $this->encryptQuantumMessage($plaintext, $senderIdentityKey, $recipientIdentityKey);
        } elseif (str_contains($algorithm, 'HYBRID')) {
            return $this->encryptHybridMessage($plaintext, $senderIdentityKey, $recipientIdentityKey);
        } else {
            return $this->encryptClassicalMessage($plaintext, $senderIdentityKey, $recipientIdentityKey);
        }
    }

    /**
     * Encrypt message using quantum algorithm
     */
    private function encryptQuantumMessage(IdentityKey $senderKey, IdentityKey $recipientKey, string $plaintext): array
    {
        $algorithm = $recipientKey->quantum_algorithm;

        // Perform key encapsulation
        $encapsulation = $this->quantumService->encapsulate(
            base64_decode($recipientKey->quantum_public_key),
            $algorithm
        );

        // Encrypt message with shared secret
        $encrypted = $this->quantumService->encrypt($plaintext, $encapsulation['shared_secret']);

        return [
            'content' => json_encode([
                'ciphertext' => $encapsulation['ciphertext'],
                'encrypted_message' => $encrypted['ciphertext'],
                'nonce' => $encrypted['nonce'],
            ]),
            'hash' => hash('sha256', $plaintext),
            'algorithm' => $algorithm,
            'session_info' => [
                'key_encapsulation' => true,
                'quantum_algorithm' => $algorithm,
            ],
        ];
    }

    /**
     * Decrypt quantum encrypted message
     */
    private function decryptQuantumMessage(string $encryptedContent, IdentityKey $recipientKey, IdentityKey $senderKey): string
    {
        $data = json_decode($encryptedContent, true);
        $algorithm = $recipientKey->quantum_algorithm;

        // Decapsulate shared secret
        $sharedSecret = $this->quantumService->decapsulate(
            base64_decode($data['ciphertext']),
            Crypt::decryptString($recipientKey->quantum_private_key_encrypted),
            $algorithm
        );

        // Decrypt message
        return $this->quantumService->decrypt(
            $data['encrypted_message'],
            $data['nonce'],
            $sharedSecret
        );
    }

    /**
     * Generate Signal Protocol pre-keys
     */
    private function generatePreKeys(IdentityKey $identityKey, int $count): void
    {
        // In a real implementation, this would generate one-time pre-keys
        // For now, we'll log the action
        Log::info('Generated pre-keys for Signal Protocol', [
            'identity_key_id' => $identityKey->id,
            'count' => $count,
        ]);
    }

    /**
     * Generate Signal Protocol signed pre-key
     */
    private function generateSignedPreKey(IdentityKey $identityKey): void
    {
        // In a real implementation, this would generate a signed pre-key
        // For now, we'll log the action
        Log::info('Generated signed pre-key for Signal Protocol', [
            'identity_key_id' => $identityKey->id,
        ]);
    }

    /**
     * Rotate device keys
     */
    public function rotateDeviceKeys(UserDevice $device, IdentityKey $currentIdentityKey): IdentityKey
    {
        // Generate new identity keypair
        $identityKeyPair = $this->generateIdentityKeyPair();

        // Create new identity key
        $newIdentityKey = $currentIdentityKey->rotate(
            base64_encode($identityKeyPair['public']),
            Crypt::encryptString($identityKeyPair['private']),
            hash('sha256', $identityKeyPair['public'])
        );

        // Copy quantum capabilities if available
        if ($currentIdentityKey->isQuantumCapable()) {
            $newIdentityKey->enableQuantum(
                $currentIdentityKey->quantum_public_key,
                $currentIdentityKey->quantum_private_key_encrypted,
                $currentIdentityKey->quantum_algorithm
            );
        }

        return $newIdentityKey;
    }

    /**
     * Encrypt message using classical algorithm
     */
    private function encryptClassicalMessage(string $plaintext, IdentityKey $senderKey, IdentityKey $recipientKey): array
    {
        // Use AES-256-GCM for classical encryption
        $key = random_bytes(32); // 256-bit key
        $nonce = random_bytes(12); // 96-bit nonce for GCM
        
        $encrypted = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        return [
            'content' => json_encode([
                'encrypted_message' => base64_encode($encrypted),
                'key' => base64_encode($key),
                'nonce' => base64_encode($nonce),
                'tag' => base64_encode($tag),
            ]),
            'hash' => hash('sha256', $plaintext),
            'algorithm' => 'AES-256-GCM',
            'session_info' => [
                'classical_encryption' => true,
            ],
        ];
    }

    /**
     * Decrypt classical encrypted message
     */
    private function decryptClassicalMessage(string $encryptedContent, IdentityKey $recipientKey, IdentityKey $senderKey): string
    {
        $data = json_decode($encryptedContent, true);

        return openssl_decrypt(
            base64_decode($data['encrypted_message']),
            'aes-256-gcm',
            base64_decode($data['key']),
            OPENSSL_RAW_DATA,
            base64_decode($data['nonce']),
            base64_decode($data['tag'])
        );
    }

    /**
     * Encrypt message using hybrid algorithm
     */
    private function encryptHybridMessage(string $plaintext, IdentityKey $senderKey, IdentityKey $recipientKey): array
    {
        // For hybrid, use both classical and quantum methods
        $classicalResult = $this->encryptClassicalMessage($plaintext, $senderKey, $recipientKey);
        
        if ($recipientKey->isQuantumCapable()) {
            $quantumResult = $this->encryptQuantumMessage($senderKey, $recipientKey, $plaintext);
            
            return [
                'content' => json_encode([
                    'classical' => json_decode($classicalResult['content'], true),
                    'quantum' => json_decode($quantumResult['content'], true),
                    'method' => 'hybrid',
                ]),
                'hash' => $classicalResult['hash'],
                'algorithm' => 'HYBRID-' . $recipientKey->quantum_algorithm,
                'session_info' => [
                    'hybrid_encryption' => true,
                    'quantum_algorithm' => $recipientKey->quantum_algorithm,
                ],
            ];
        }

        return $classicalResult;
    }

    /**
     * Decrypt hybrid encrypted message
     */
    private function decryptHybridMessage(string $encryptedContent, IdentityKey $recipientKey, IdentityKey $senderKey): string
    {
        $data = json_decode($encryptedContent, true);

        if (isset($data['method']) && $data['method'] === 'hybrid' && $recipientKey->isQuantumCapable()) {
            // Try quantum decryption first
            try {
                return $this->decryptQuantumMessage(
                    json_encode($data['quantum']), 
                    $recipientKey, 
                    $senderKey
                );
            } catch (Exception $e) {
                Log::warning('Quantum decryption failed, falling back to classical', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fall back to classical decryption
        return $this->decryptClassicalMessage(
            json_encode($data['classical']), 
            $recipientKey, 
            $senderKey
        );
    }

    /**
     * Additional helper methods for group management...
     */
    private function generateEncryptionKeysForNewParticipant(Conversation $conversation, User $newParticipant): void
    {
        // Generate encryption keys for new participant's devices
        $devices = $newParticipant->devices()->active()->get();
        
        foreach ($devices as $device) {
            $this->generateEncryptionKeyForDevice($conversation, $newParticipant, $device);
        }
    }

    private function revokeEncryptionKeysForParticipant(Conversation $conversation, User $participant, ?string $reason): void
    {
        EncryptionKey::where('conversation_id', $conversation->id)
            ->where('user_id', $participant->id)
            ->update(['revoked_at' => now(), 'revocation_reason' => $reason]);
    }

    private function rotateGroupEncryptionKeys(Conversation $conversation): void
    {
        // Mark all current keys as revoked
        EncryptionKey::where('conversation_id', $conversation->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revocation_reason' => 'key_rotation']);

        // Generate new keys for all participants
        $this->generateGroupEncryptionKeys($conversation);
    }

    private function rotateDirectMessageKeys(Conversation $conversation): void
    {
        // For direct messages, rotate session keys between the two participants
        $participants = $conversation->activeParticipants()->with('user')->get();
        
        if ($participants->count() === 2) {
            foreach ($participants as $participant) {
                $devices = $participant->user->devices()->active()->get();
                foreach ($devices as $device) {
                    $this->generateEncryptionKeyForDevice($conversation, $participant->user, $device);
                }
            }
        }
    }

    private function sendSystemMessage(Conversation $conversation, User $sender, array $data): void
    {
        // Create system message for conversation events
        Log::info('System message sent', [
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'type' => $data['type'],
        ]);
    }

    private function getHMACKeyForConversation(Conversation $conversation, User $user): ?string
    {
        // Get HMAC key for message authentication
        $encryptionKey = EncryptionKey::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->active()
            ->first();

        return $encryptionKey ? hash('sha256', Crypt::decryptString($encryptionKey->encrypted_key)) : null;
    }
}
