<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\Chat\Participant;
use App\Models\User;
use App\Services\ChatEncryptionService;
use App\Services\MultiDeviceEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->multiDeviceService = new MultiDeviceEncryptionService($this->encryptionService);

    // Create test users
    $this->users = [];
    for ($i = 1; $i <= 10; $i++) {
        $this->users[] = User::factory()->create(['name' => "User {$i}"]);
    }

    // Create group conversation
    $this->groupConversation = Conversation::factory()->create([
        'type' => 'group',
        'name' => 'Test Group Chat',
        'created_by' => $this->users[0]->id,
    ]);

    // Add participants with different roles
    $this->groupConversation->participants()->create([
        'user_id' => $this->users[0]->id,
        'role' => 'admin',
    ]);

    for ($i = 1; $i <= 5; $i++) {
        $this->groupConversation->participants()->create([
            'user_id' => $this->users[$i]->id,
            'role' => 'member',
        ]);
    }
});

describe('E2EE Group Chat and Multi-Participant Encryption', function () {
    describe('Group Key Distribution', function () {
        it('distributes encryption keys to all group participants', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $participantKeys = [];
            $encryptionKeys = [];

            // Generate key pairs for all participants
            foreach ($this->users as $index => $user) {
                if ($index <= 5) { // First 6 users are participants
                    $keyPair = $this->encryptionService->generateKeyPair();
                    $user->update(['public_key' => $keyPair['public_key']]);
                    $participantKeys[$user->id] = $keyPair;
                }
            }

            // Create encryption keys for all participants
            foreach ($this->groupConversation->participants as $participant) {
                $user = $participant->user;
                $encryptionKey = EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $symmetricKey,
                    $user->public_key
                );
                $encryptionKeys[] = $encryptionKey;
            }

            expect(count($encryptionKeys))->toBe(6); // Admin + 5 members

            // Verify each participant can decrypt the symmetric key
            foreach ($encryptionKeys as $key) {
                $userId = $key->user_id;
                $privateKey = $participantKeys[$userId]['private_key'];

                $decryptedKey = $key->decryptSymmetricKey($privateKey);
                expect($decryptedKey)->toBe($symmetricKey);
            }

            // Test group message encryption/decryption
            $groupMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $this->users[0]->id,
                'Hello everyone in the group!',
                $symmetricKey
            );

            // All participants should be able to decrypt
            foreach ($participantKeys as $userId => $keyPair) {
                $userKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                    ->where('user_id', $userId)
                    ->first();

                $userSymmetricKey = $userKey->decryptSymmetricKey($keyPair['private_key']);
                $decryptedMessage = $groupMessage->decryptContent($userSymmetricKey);
                expect($decryptedMessage)->toBe('Hello everyone in the group!');
            }
        });

        it('handles group key rotation for all participants', function () {
            $oldSymmetricKey = $this->encryptionService->generateSymmetricKey();
            $participantKeys = [];

            // Setup initial keys
            foreach ($this->groupConversation->participants as $participant) {
                $user = $participant->user;
                $keyPair = $this->encryptionService->generateKeyPair();
                $user->update(['public_key' => $keyPair['public_key']]);
                $participantKeys[$user->id] = $keyPair;

                EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $oldSymmetricKey,
                    $user->public_key
                );
            }

            // Create message with old key
            $oldMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $this->users[0]->id,
                'Message with old key',
                $oldSymmetricKey
            );

            // Rotate keys - deactivate old ones
            EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->update(['is_active' => false]);

            // Generate new symmetric key
            $newSymmetricKey = $this->encryptionService->rotateSymmetricKey($this->groupConversation->id);

            // Create new encryption keys for all participants
            $newKeys = [];
            foreach ($this->groupConversation->participants as $participant) {
                $user = $participant->user;
                $newKey = EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $newSymmetricKey,
                    $user->public_key,
                    2 // New key version
                );
                $newKeys[] = $newKey;
            }

            // Create message with new key
            $newMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $this->users[1]->id,
                'Message with new key',
                $newSymmetricKey
            );

            // Verify key rotation worked
            expect(count($newKeys))->toBe(6);

            foreach ($newKeys as $key) {
                expect($key->is_active)->toBeTrue();
                expect($key->key_version)->toBe(2);

                $userId = $key->user_id;
                $privateKey = $participantKeys[$userId]['private_key'];
                $decryptedKey = $key->decryptSymmetricKey($privateKey);
                expect($decryptedKey)->toBe($newSymmetricKey);
            }

            // Old message should still be decryptable with old keys (for message history)
            $oldUserKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->where('user_id', $this->users[0]->id)
                ->where('key_version', 1)
                ->first();

            if ($oldUserKey) {
                $oldDecryptedKey = $oldUserKey->decryptSymmetricKey($participantKeys[$this->users[0]->id]['private_key']);
                $oldDecryptedMessage = $oldMessage->decryptContent($oldDecryptedKey);
                expect($oldDecryptedMessage)->toBe('Message with old key');
            }

            // New message should be decryptable with new keys
            $newUserKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->where('user_id', $this->users[1]->id)
                ->where('key_version', 2)
                ->first();

            $newDecryptedKey = $newUserKey->decryptSymmetricKey($participantKeys[$this->users[1]->id]['private_key']);
            $newDecryptedMessage = $newMessage->decryptContent($newDecryptedKey);
            expect($newDecryptedMessage)->toBe('Message with new key');
        });
    });

    describe('Dynamic Group Membership', function () {
        it('adds new participants to existing encrypted group', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $participantKeys = [];

            // Setup initial group with encryption
            foreach ($this->groupConversation->participants as $participant) {
                $user = $participant->user;
                $keyPair = $this->encryptionService->generateKeyPair();
                $user->update(['public_key' => $keyPair['public_key']]);
                $participantKeys[$user->id] = $keyPair;

                EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $symmetricKey,
                    $user->public_key
                );
            }

            // Send some messages before adding new member
            $preMessages = [];
            for ($i = 0; $i < 3; $i++) {
                $preMessages[] = Message::createEncrypted(
                    $this->groupConversation->id,
                    $this->users[$i]->id,
                    "Pre-addition message {$i}",
                    $symmetricKey
                );
            }

            // Add new participant
            $newUser = $this->users[6]; // User not previously in group
            $newUserKeyPair = $this->encryptionService->generateKeyPair();
            $newUser->update(['public_key' => $newUserKeyPair['public_key']]);

            $this->groupConversation->participants()->create([
                'user_id' => $newUser->id,
                'role' => 'member',
                'joined_at' => now(),
            ]);

            // Create encryption key for new participant
            $newUserEncKey = EncryptionKey::createForUser(
                $this->groupConversation->id,
                $newUser->id,
                $symmetricKey,
                $newUser->public_key
            );

            // New user should be able to decrypt the symmetric key
            $newUserSymmetricKey = $newUserEncKey->decryptSymmetricKey($newUserKeyPair['private_key']);
            expect($newUserSymmetricKey)->toBe($symmetricKey);

            // Send message after adding new member
            $postMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $newUser->id,
                'Hello, I just joined the group!',
                $symmetricKey
            );

            // All participants (including new one) should be able to decrypt new messages
            $allParticipants = $this->groupConversation->participants()->with('user')->get();
            expect($allParticipants->count())->toBe(7); // Original 6 + new one

            foreach ($allParticipants as $participant) {
                $user = $participant->user;
                $userKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($user->id === $newUser->id) {
                    $privateKey = $newUserKeyPair['private_key'];
                } else {
                    $privateKey = $participantKeys[$user->id]['private_key'];
                }

                $userSymmetricKey = $userKey->decryptSymmetricKey($privateKey);
                $decryptedMessage = $postMessage->decryptContent($userSymmetricKey);
                expect($decryptedMessage)->toBe('Hello, I just joined the group!');
            }

            // Note: New user typically wouldn't have access to pre-addition messages
            // This depends on business logic - some systems provide message history,
            // others don't for security reasons
        });

        it('removes participants and handles key rotation', function () {
            $oldSymmetricKey = $this->encryptionService->generateSymmetricKey();
            $participantKeys = [];

            // Setup group with encryption
            foreach ($this->groupConversation->participants as $participant) {
                $user = $participant->user;
                $keyPair = $this->encryptionService->generateKeyPair();
                $user->update(['public_key' => $keyPair['public_key']]);
                $participantKeys[$user->id] = $keyPair;

                EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $oldSymmetricKey,
                    $user->public_key
                );
            }

            // Send message before removal
            $preRemovalMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $this->users[0]->id,
                'Message before member removal',
                $oldSymmetricKey
            );

            // Remove a participant
            $removedUser = $this->users[5];
            $this->groupConversation->participants()
                ->where('user_id', $removedUser->id)
                ->update(['left_at' => now()]);

            // Deactivate removed user's encryption key
            EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->where('user_id', $removedUser->id)
                ->update(['is_active' => false]);

            // Rotate keys for security (removed user shouldn't access future messages)
            EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->where('user_id', '!=', $removedUser->id)
                ->update(['is_active' => false]);

            $newSymmetricKey = $this->encryptionService->rotateSymmetricKey($this->groupConversation->id);

            // Create new keys for remaining participants only
            $remainingParticipants = $this->groupConversation->participants()
                ->whereNull('left_at')
                ->with('user')
                ->get();

            foreach ($remainingParticipants as $participant) {
                $user = $participant->user;
                EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $newSymmetricKey,
                    $user->public_key,
                    2 // New version
                );
            }

            // Send message after removal with new key
            $postRemovalMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $this->users[0]->id,
                'Message after member removal',
                $newSymmetricKey
            );

            // Verify remaining participants can decrypt new message
            foreach ($remainingParticipants as $participant) {
                $user = $participant->user;
                $newKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                    ->where('user_id', $user->id)
                    ->where('key_version', 2)
                    ->first();

                $privateKey = $participantKeys[$user->id]['private_key'];
                $newUserSymmetricKey = $newKey->decryptSymmetricKey($privateKey);
                $decryptedMessage = $postRemovalMessage->decryptContent($newUserSymmetricKey);
                expect($decryptedMessage)->toBe('Message after member removal');
            }

            // Verify removed user cannot decrypt new messages (no active key)
            $removedUserActiveKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->where('user_id', $removedUser->id)
                ->where('is_active', true)
                ->first();

            expect($removedUserActiveKey)->toBeNull();
        });
    });

    describe('Large Group Management', function () {
        it('handles large group with many participants efficiently', function () {
            // Create large group (50 participants)
            $largeGroup = Conversation::factory()->create([
                'type' => 'group',
                'name' => 'Large Test Group',
                'created_by' => $this->users[0]->id,
            ]);

            $participants = [];
            $participantKeys = [];

            // Add admin
            $largeGroup->participants()->create([
                'user_id' => $this->users[0]->id,
                'role' => 'admin',
            ]);
            $participants[] = $this->users[0];

            // Add regular participants
            for ($i = 1; $i < 50; $i++) {
                if ($i < 10) {
                    $user = $this->users[$i];
                } else {
                    $user = User::factory()->create(['name' => "Large Group User {$i}"]);
                }

                $largeGroup->participants()->create([
                    'user_id' => $user->id,
                    'role' => 'member',
                ]);
                $participants[] = $user;
            }

            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $startTime = microtime(true);

            // Generate keys for all participants
            foreach ($participants as $user) {
                $keyPair = $this->encryptionService->generateKeyPair();
                $user->update(['public_key' => $keyPair['public_key']]);
                $participantKeys[$user->id] = $keyPair;

                EncryptionKey::createForUser(
                    $largeGroup->id,
                    $user->id,
                    $symmetricKey,
                    $user->public_key
                );
            }

            $keySetupTime = microtime(true) - $startTime;

            // Should complete key setup within reasonable time
            // With 2048-bit keys in testing environment, this should be much faster
            expect($keySetupTime)->toBeLessThan(30.0); // 30 seconds for 50 users with 2048-bit keys
            expect(count($participants))->toBe(50);

            // Verify encryption key count
            $keyCount = EncryptionKey::where('conversation_id', $largeGroup->id)->count();
            expect($keyCount)->toBe(50);

            // Test message broadcasting to large group
            $messageStartTime = microtime(true);

            $broadcastMessage = Message::createEncrypted(
                $largeGroup->id,
                $participants[0]->id,
                'Broadcast message to large group',
                $symmetricKey
            );

            $messageTime = microtime(true) - $messageStartTime;
            expect($messageTime)->toBeLessThan(5.0);

            // Sample test decryption for some participants
            $sampleUsers = array_slice($participants, 0, 10);
            foreach ($sampleUsers as $user) {
                $userKey = EncryptionKey::where('conversation_id', $largeGroup->id)
                    ->where('user_id', $user->id)
                    ->first();

                $privateKey = $participantKeys[$user->id]['private_key'];
                $userSymmetricKey = $userKey->decryptSymmetricKey($privateKey);
                $decryptedMessage = $broadcastMessage->decryptContent($userSymmetricKey);
                expect($decryptedMessage)->toBe('Broadcast message to large group');
            }
        });

        it('handles role-based access in encrypted groups', function () {
            // Setup group with different roles
            $adminUser = $this->users[0];
            $secondAdminUser = $this->users[1]; // Will be another admin
            $memberUser = $this->users[2];
            $anotherMemberUser = $this->users[3]; // Will be another member

            // Update participant roles - both admins and members
            $this->groupConversation->participants()
                ->where('user_id', $secondAdminUser->id)
                ->update(['role' => 'admin']);

            $this->groupConversation->participants()
                ->where('user_id', $anotherMemberUser->id)
                ->update(['role' => 'member']);

            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $allUsers = [$adminUser, $secondAdminUser, $memberUser, $anotherMemberUser];
            $userKeys = [];

            // Setup encryption for all users
            foreach ($allUsers as $user) {
                $keyPair = $this->encryptionService->generateKeyPair();
                $user->update(['public_key' => $keyPair['public_key']]);
                $userKeys[$user->id] = $keyPair;

                EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $symmetricKey,
                    $user->public_key
                );
            }

            // All users should be able to read messages (encryption doesn't enforce permissions)
            $testMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $adminUser->id,
                'Message from admin to all',
                $symmetricKey
            );

            foreach ($allUsers as $user) {
                $userEncKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                    ->where('user_id', $user->id)
                    ->first();

                $privateKey = $userKeys[$user->id]['private_key'];
                $userSymmetricKey = $userEncKey->decryptSymmetricKey($privateKey);
                $decryptedMessage = $testMessage->decryptContent($userSymmetricKey);
                expect($decryptedMessage)->toBe('Message from admin to all');
            }

            // Role-based sending restrictions would be enforced at application level,
            // not at encryption level. Encryption ensures confidentiality, not authorization.
            $memberMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $memberUser->id,
                'Message from member',
                $symmetricKey
            );

            // Both admins should be able to read member's message
            $adminKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->where('user_id', $adminUser->id)
                ->first();

            $adminPrivateKey = $userKeys[$adminUser->id]['private_key'];
            $adminSymmetricKey = $adminKey->decryptSymmetricKey($adminPrivateKey);
            $decryptedMemberMessage = $memberMessage->decryptContent($adminSymmetricKey);
            expect($decryptedMemberMessage)->toBe('Message from member');
        });
    });

    describe('Group Message Threading and Replies', function () {
        it('handles encrypted message threads in groups', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $participantKeys = [];

            // Setup encryption for first 3 participants
            for ($i = 0; $i < 3; $i++) {
                $user = $this->users[$i];
                $keyPair = $this->encryptionService->generateKeyPair();
                $user->update(['public_key' => $keyPair['public_key']]);
                $participantKeys[$user->id] = $keyPair;

                EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $symmetricKey,
                    $user->public_key
                );
            }

            // Create parent message
            $parentMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $this->users[0]->id,
                'This is the main topic for discussion',
                $symmetricKey
            );

            // Create threaded replies
            $replies = [];
            for ($i = 1; $i < 3; $i++) {
                $replyContent = "Reply {$i} to the main topic";
                $reply = Message::createEncrypted(
                    $this->groupConversation->id,
                    $this->users[$i]->id,
                    $replyContent,
                    $symmetricKey,
                    ['reply_to_id' => $parentMessage->id]
                );
                $replies[] = $reply;
            }

            // Verify thread structure and encryption
            expect(count($replies))->toBe(2);

            foreach ($replies as $reply) {
                expect($reply->reply_to_id)->toBe($parentMessage->id);

                // All participants should be able to decrypt replies
                foreach ($participantKeys as $userId => $keyPair) {
                    $userKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                        ->where('user_id', $userId)
                        ->first();

                    $userSymmetricKey = $userKey->decryptSymmetricKey($keyPair['private_key']);
                    $decryptedReply = $reply->decryptContent($userSymmetricKey);
                    expect($decryptedReply)->toStartWith('Reply');
                }
            }

            // Verify parent message is also decryptable
            foreach ($participantKeys as $userId => $keyPair) {
                $userKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                    ->where('user_id', $userId)
                    ->first();

                $userSymmetricKey = $userKey->decryptSymmetricKey($keyPair['private_key']);
                $decryptedParent = $parentMessage->decryptContent($userSymmetricKey);
                expect($decryptedParent)->toBe('This is the main topic for discussion');
            }
        });

        it('supports encrypted group reactions and interactions', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $participantKeys = [];

            // Setup encryption for participants
            for ($i = 0; $i < 4; $i++) {
                $user = $this->users[$i];
                $keyPair = $this->encryptionService->generateKeyPair();
                $user->update(['public_key' => $keyPair['public_key']]);
                $participantKeys[$user->id] = $keyPair;

                EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $symmetricKey,
                    $user->public_key
                );
            }

            // Create message to react to
            $originalMessage = Message::createEncrypted(
                $this->groupConversation->id,
                $this->users[0]->id,
                'This message will get reactions!',
                $symmetricKey
            );

            // Add reactions from different users
            $reactions = [];
            $emojis = ['👍', '❤️', '😂', '🎉'];

            for ($i = 1; $i < 4; $i++) {
                $reaction = \App\Models\Chat\MessageReaction::create([
                    'message_id' => $originalMessage->id,
                    'user_id' => $this->users[$i]->id,
                    'emoji' => $emojis[$i],
                ]);
                $reactions[] = $reaction;
            }

            // Verify reactions are stored
            expect(count($reactions))->toBe(3);

            $storedReactions = \App\Models\Chat\MessageReaction::where('message_id', $originalMessage->id)->get();
            expect($storedReactions->count())->toBe(3);

            // Verify original message can still be decrypted by all participants
            foreach ($participantKeys as $userId => $keyPair) {
                $userKey = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                    ->where('user_id', $userId)
                    ->first();

                $userSymmetricKey = $userKey->decryptSymmetricKey($keyPair['private_key']);
                $decryptedMessage = $originalMessage->decryptContent($userSymmetricKey);
                expect($decryptedMessage)->toBe('This message will get reactions!');
            }

            // Verify reactions are linked to correct message
            foreach ($reactions as $reaction) {
                expect($reaction->message_id)->toBe($originalMessage->id);
                expect(in_array($reaction->emoji, $emojis))->toBeTrue();
            }
        });
    });

    describe('Group Encryption Key Versioning', function () {
        it('maintains message history across key versions', function () {
            $participantKeys = [];
            $messages = [];

            // Setup initial participants
            for ($i = 0; $i < 3; $i++) {
                $user = $this->users[$i];
                $keyPair = $this->encryptionService->generateKeyPair();
                $user->update(['public_key' => $keyPair['public_key']]);
                $participantKeys[$user->id] = $keyPair;
            }

            // Version 1: Initial keys
            $v1SymmetricKey = $this->encryptionService->generateSymmetricKey();
            foreach ($participantKeys as $userId => $keyPair) {
                EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $userId,
                    $v1SymmetricKey,
                    $keyPair['public_key'],
                    1
                );
            }

            // Send messages with v1
            for ($i = 0; $i < 3; $i++) {
                $messages['v1'][] = Message::createEncrypted(
                    $this->groupConversation->id,
                    $this->users[$i]->id,
                    "Version 1 message {$i}",
                    $v1SymmetricKey
                );
            }

            // Version 2: Key rotation
            EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->update(['is_active' => false]);

            $v2SymmetricKey = $this->encryptionService->rotateSymmetricKey($this->groupConversation->id);
            foreach ($participantKeys as $userId => $keyPair) {
                EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $userId,
                    $v2SymmetricKey,
                    $keyPair['public_key'],
                    2
                );
            }

            // Send messages with v2
            for ($i = 0; $i < 2; $i++) {
                $messages['v2'][] = Message::createEncrypted(
                    $this->groupConversation->id,
                    $this->users[$i]->id,
                    "Version 2 message {$i}",
                    $v2SymmetricKey
                );
            }

            // Verify participants can decrypt messages from both versions
            foreach ($participantKeys as $userId => $keyPair) {
                // Test v1 messages
                $v1Key = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                    ->where('user_id', $userId)
                    ->where('key_version', 1)
                    ->first();

                if ($v1Key) {
                    $v1UserSymmetricKey = $v1Key->decryptSymmetricKey($keyPair['private_key']);

                    foreach ($messages['v1'] as $message) {
                        $decrypted = $message->decryptContent($v1UserSymmetricKey);
                        expect($decrypted)->toStartWith('Version 1 message');
                    }
                }

                // Test v2 messages
                $v2Key = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                    ->where('user_id', $userId)
                    ->where('key_version', 2)
                    ->first();

                $v2UserSymmetricKey = $v2Key->decryptSymmetricKey($keyPair['private_key']);

                foreach ($messages['v2'] as $message) {
                    $decrypted = $message->decryptContent($v2UserSymmetricKey);
                    expect($decrypted)->toStartWith('Version 2 message');
                }
            }

            // Verify key version progression
            expect(count($messages['v1']))->toBe(3);
            expect(count($messages['v2']))->toBe(2);
        });

        it('handles key version compatibility checks', function () {
            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            $user = $this->users[0];
            $keyPair = $this->encryptionService->generateKeyPair();
            $user->update(['public_key' => $keyPair['public_key']]);

            // Create keys with different versions
            $versions = [1, 2, 3];
            $keys = [];

            foreach ($versions as $version) {
                // Deactivate previous version
                EncryptionKey::where('conversation_id', $this->groupConversation->id)
                    ->where('user_id', $user->id)
                    ->update(['is_active' => false]);

                $key = EncryptionKey::createForUser(
                    $this->groupConversation->id,
                    $user->id,
                    $symmetricKey,
                    $user->public_key,
                    $version
                );
                $keys[] = $key;
            }

            // Only latest version should be active
            $activeKeys = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->get();

            expect($activeKeys->count())->toBe(1);
            expect($activeKeys->first()->key_version)->toBe(3);

            // All versions should exist for historical access
            $allKeys = EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->where('user_id', $user->id)
                ->get();

            expect($allKeys->count())->toBe(3);

            // Each key should be decryptable
            foreach ($allKeys as $key) {
                $decryptedKey = $key->decryptSymmetricKey($keyPair['private_key']);
                expect($decryptedKey)->toBe($symmetricKey);
            }
        });
    });
});
