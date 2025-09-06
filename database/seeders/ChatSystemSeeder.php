<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\EncryptionKey;
use App\Models\Signal\IdentityKey;
use App\Services\ChatEncryptionService;
use App\Services\SignalProtocolService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatSystemSeeder extends Seeder
{
    /**
     * Run the E2EE chat system seeder
     */
    public function run(): void
    {
        $this->command->info('🔐 Seeding E2EE Chat System...');
        
        DB::beginTransaction();
        
        try {
            // Create test users if they don't exist
            $users = $this->createTestUsers();
            
            // Create devices for users
            $devices = $this->createTestDevices($users);
            
            // Initialize Signal Protocol for users
            $this->initializeSignalProtocol($users, $devices);
            
            // Create conversations
            $conversations = $this->createTestConversations($users);
            
            // Create sample messages
            $this->createSampleMessages($conversations, $users);
            
            // Create polls and surveys
            $this->createSamplePolls($conversations, $users);
            
            DB::commit();
            
            $this->command->info('✅ E2EE Chat System seeded successfully!');
            $this->command->info('   Users: ' . count($users));
            $this->command->info('   Devices: ' . count($devices));
            $this->command->info('   Conversations: ' . count($conversations));
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Chat system seeding failed: ' . $e->getMessage());
            Log::error('Chat seeding error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Create test users for chat system
     */
    private function createTestUsers(): array
    {
        $users = [];
        
        $testUsers = [
            [
                'name' => 'Alice Crypto',
                'email' => 'alice@e2ee.test',
                'email_verified_at' => now(),
                'password' => 'password123', // Will be hashed by Laravel automatically
            ],
            [
                'name' => 'Bob Quantum',
                'email' => 'bob@e2ee.test',
                'email_verified_at' => now(),
                'password' => 'password123', // Will be hashed by Laravel automatically
            ],
            [
                'name' => 'Carol Signal',
                'email' => 'carol@e2ee.test',
                'email_verified_at' => now(),
                'password' => 'password123', // Will be hashed by Laravel automatically
            ],
            [
                'name' => 'David E2EE',
                'email' => 'david@e2ee.test',
                'email_verified_at' => now(),
                'password' => 'password123', // Will be hashed by Laravel automatically
            ],
        ];

        foreach ($testUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            $users[] = $user;
        }

        return $users;
    }

    /**
     * Create test devices for users
     */
    private function createTestDevices(array $users): array
    {
        $devices = [];
        
        foreach ($users as $user) {
            // Create a primary device for each user
            $device = UserDevice::firstOrCreate([
                'user_id' => $user->id,
                'device_name' => $user->name . '\'s Primary Device',
            ], [
                'device_type' => 'desktop',
                'device_fingerprint' => hash('sha256', $user->id . 'primary_device'),
                'is_trusted' => true,
                'is_active' => true,
                'last_used_at' => now(),
                'device_info' => [
                    'user_agent' => 'E2EE-Chat-Test/1.0',
                    'platform' => 'Linux',
                    'browser' => 'Chrome',
                ],
            ]);
            
            $devices[] = $device;

            // Create a secondary mobile device for some users
            if (in_array($user->email, ['alice@e2ee.test', 'bob@e2ee.test'])) {
                $mobileDevice = UserDevice::firstOrCreate([
                    'user_id' => $user->id,
                    'device_name' => $user->name . '\'s Mobile',
                ], [
                    'device_type' => 'mobile',
                    'device_fingerprint' => hash('sha256', $user->id . 'mobile_device'),
                    'is_trusted' => true,
                    'is_active' => true,
                    'last_used_at' => now(),
                    'device_info' => [
                        'user_agent' => 'E2EE-Mobile/1.0',
                        'platform' => 'Android',
                        'browser' => 'Chrome Mobile',
                    ],
                ]);
                
                $devices[] = $mobileDevice;
            }
        }

        return $devices;
    }

    /**
     * Initialize Signal Protocol for test users
     */
    private function initializeSignalProtocol(array $users, array $devices): void
    {
        $signalService = app(SignalProtocolService::class);
        
        foreach ($devices as $device) {
            // Check if identity key already exists
            $existingKey = IdentityKey::where('user_id', $device->user_id)
                ->where('device_id', $device->id)
                ->first();
                
            if (!$existingKey) {
                $user = User::find($device->user_id);
                $signalService->initializeDevice($user, $device, [
                    'enable_quantum' => true,
                    'quantum_algorithm' => 'ML-KEM-768',
                ]);
            }
        }
    }

    /**
     * Create test conversations
     */
    private function createTestConversations(array $users): array
    {
        $conversations = [];
        
        // Create a direct message conversation
        $directConversation = Conversation::firstOrCreate([
            'type' => 'direct',
            'created_by_user_id' => $users[0]->id,
            'created_by_device_id' => $users[0]->devices()->first()->id,
        ], [
            'name' => null,
            'description' => 'Direct message between Alice and Bob',
            'settings' => [
                'encryption_algorithm' => 'ML-KEM-768',
                'disappearing_messages' => false,
                'enable_reactions' => true,
                'enable_threading' => true,
            ],
            'is_active' => true,
            'last_activity_at' => now(),
        ]);

        // Add participants to direct conversation
        $directConversation->participants()->firstOrCreate([
            'user_id' => $users[0]->id,
        ], [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $directConversation->participants()->firstOrCreate([
            'user_id' => $users[1]->id,
        ], [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $conversations[] = $directConversation;

        // Create a group conversation
        $groupConversation = Conversation::firstOrCreate([
            'type' => 'group',
            'name' => 'E2EE Development Team',
        ], [
            'created_by_user_id' => $users[0]->id,
            'created_by_device_id' => $users[0]->devices()->first()->id,
            'description' => 'Secure group chat for the development team',
            'settings' => [
                'encryption_algorithm' => 'signal',
                'disappearing_messages' => false,
                'enable_reactions' => true,
                'enable_threading' => true,
                'enable_polls' => true,
            ],
            'is_active' => true,
            'last_activity_at' => now(),
        ]);

        // Add all users to group conversation
        foreach ($users as $index => $user) {
            $groupConversation->participants()->firstOrCreate([
                'user_id' => $user->id,
            ], [
                'role' => $index === 0 ? 'admin' : 'member',
                'joined_at' => now(),
            ]);
        }

        $conversations[] = $groupConversation;

        // Create a quantum-safe channel
        $channelConversation = Conversation::firstOrCreate([
            'type' => 'channel',
            'name' => 'Quantum Security Updates',
        ], [
            'created_by_user_id' => $users[0]->id,
            'created_by_device_id' => $users[0]->devices()->first()->id,
            'description' => 'Latest updates on quantum-resistant cryptography',
            'settings' => [
                'encryption_algorithm' => 'ML-KEM-1024',
                'disappearing_messages' => false,
                'enable_reactions' => true,
                'enable_threading' => false,
                'read_only' => false,
            ],
            'is_active' => true,
            'last_activity_at' => now(),
        ]);

        // Add users to channel
        foreach (array_slice($users, 0, 3) as $index => $user) {
            $channelConversation->participants()->firstOrCreate([
                'user_id' => $user->id,
            ], [
                'role' => $index === 0 ? 'admin' : 'member',
                'joined_at' => now(),
            ]);
        }

        $conversations[] = $channelConversation;

        return $conversations;
    }

    /**
     * Create sample messages for conversations
     */
    private function createSampleMessages(array $conversations, array $users): void
    {
        $sampleMessages = [
            'Hello! Testing our E2EE chat system.',
            'The quantum cryptography implementation looks great!',
            'Signal Protocol integration is working smoothly.',
            'Multi-device synchronization is functioning correctly.',
            'File encryption and sharing capabilities are impressive.',
            'Real-time messaging with WebSocket is responsive.',
            'The security audit shows no vulnerabilities.',
            'Performance metrics look excellent.',
        ];

        foreach ($conversations as $conversation) {
            $participants = $conversation->participants()->with('user')->get();
            
            foreach (array_slice($sampleMessages, 0, rand(3, 6)) as $index => $messageContent) {
                $sender = $participants->random()->user;
                $senderDevice = $sender->devices()->first();
                
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'sender_device_id' => $senderDevice->id,
                    'type' => 'text',
                    'encrypted_content' => base64_encode("encrypted:{$messageContent}"),
                    'content_hash' => hash('sha256', $messageContent),
                    'encryption_algorithm' => $conversation->settings['encryption_algorithm'] ?? 'signal',
                    'encryption_version' => 1,
                    'delivery_status' => [
                        'delivered' => $participants->pluck('user.id')->toArray(),
                        'read' => [],
                    ],
                    'created_at' => now()->subMinutes(rand(1, 60)),
                ]);

                // Add some reactions to messages
                if (rand(1, 3) === 1) {
                    $reactorUser = $participants->where('user.id', '!=', $sender->id)->random()->user;
                    $emojis = ['👍', '❤️', '😊', '🔒', '⚡', '🎉'];
                    
                    $message->reactions()->create([
                        'user_id' => $reactorUser->id,
                        'emoji' => $emojis[array_rand($emojis)],
                        'created_at' => now()->subMinutes(rand(1, 30)),
                    ]);
                }
            }
        }
    }

    /**
     * Create sample polls for testing
     */
    private function createSamplePolls(array $conversations, array $users): void
    {
        $groupConversation = collect($conversations)->firstWhere('type', 'group');
        
        if ($groupConversation) {
            // Create a message for the poll
            $pollCreator = $users[0];
            $creatorDevice = $pollCreator->devices()->first();
            
            $pollMessage = Message::create([
                'conversation_id' => $groupConversation->id,
                'sender_id' => $pollCreator->id,
                'sender_device_id' => $creatorDevice->id,
                'type' => 'poll',
                'encrypted_content' => base64_encode('encrypted:poll_content'),
                'content_hash' => hash('sha256', 'poll_content'),
                'encryption_algorithm' => 'signal',
                'encryption_version' => 1,
                'created_at' => now()->subHours(2),
            ]);

            // Create the poll
            $poll = $pollMessage->poll()->create([
                'creator_id' => $pollCreator->id,
                'poll_type' => 'single_choice',
                'encrypted_question' => base64_encode('Which encryption algorithm should we prioritize?'),
                'question_hash' => hash('sha256', 'Which encryption algorithm should we prioritize?'),
                'encrypted_options' => [
                    base64_encode('ML-KEM-768 (Balanced)'),
                    base64_encode('ML-KEM-1024 (Maximum Security)'),
                    base64_encode('Hybrid RSA+ML-KEM'),
                    base64_encode('Signal Protocol'),
                ],
                'option_hashes' => [
                    hash('sha256', 'ML-KEM-768 (Balanced)'),
                    hash('sha256', 'ML-KEM-1024 (Maximum Security)'),
                    hash('sha256', 'Hybrid RSA+ML-KEM'),
                    hash('sha256', 'Signal Protocol'),
                ],
                'anonymous' => false,
                'allow_multiple_votes' => false,
                'show_results_immediately' => true,
                'expires_at' => now()->addWeek(),
                'settings' => [
                    'allow_comments' => true,
                    'notify_on_vote' => false,
                ],
            ]);

            // Add some votes
            foreach (array_slice($users, 1, 3) as $voter) {
                $poll->votes()->create([
                    'voter_id' => $voter->id,
                    'encrypted_vote_data' => json_encode([
                        'choices' => [rand(0, 3)],
                        'timestamp' => now()->subHours(rand(1, 2))->toISOString(),
                    ]),
                    'vote_hash' => hash('sha256', 'vote_data'),
                    'is_anonymous' => false,
                    'voted_at' => now()->subHours(rand(1, 2)),
                ]);
            }
        }
    }
}