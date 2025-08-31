<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Channel;
use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Participant;
use App\Models\User;
use App\Services\ChatEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ChannelController extends Controller
{
    public function __construct(private ChatEncryptionService $encryptionService)
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $query = Channel::query()
            ->active()
            ->with(['conversation.participants.user:id,name,email', 'creator:id,name,email']);

        if ($request->filled('organization_id')) {
            $query->forOrganization($request->organization_id);
        }

        if ($request->filled('visibility')) {
            $query->where('visibility', $request->visibility);
        }

        if ($request->has('user_channels') && $request->user_channels) {
            $query->forUser(auth()->id());
        } elseif (!$request->has('show_private') || !$request->show_private) {
            $query->public();
        }

        $channels = $query->orderBy('name')->paginate(20);

        return response()->json($channels);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:chat_channels,slug',
            'description' => 'nullable|string|max:1000',
            'visibility' => ['required', Rule::in(['public', 'private'])],
            'organization_id' => 'nullable|exists:organizations,id',
            'avatar_url' => 'nullable|url',
            'metadata' => 'nullable|array',
            'encryption_algorithm' => 'nullable|string|in:RSA-4096-OAEP,RSA-2048-OAEP,AES-256-GCM,ChaCha20-Poly1305',
            'key_strength' => 'nullable|integer|in:128,256,2048,4096',
        ]);

        return DB::transaction(function () use ($validated) {
            $algorithm = $validated['encryption_algorithm'] ?? 'AES-256-GCM';
            $keyStrength = $validated['key_strength'] ?? 256;

            $conversation = Conversation::create([
                'name' => $validated['name'],
                'type' => 'group',
                'description' => $validated['description'] ?? null,
                'created_by' => auth()->id(),
                'encryption_algorithm' => $algorithm,
                'key_strength' => $keyStrength,
            ]);

            $channel = Channel::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? null, // Will be auto-generated if null
                'description' => $validated['description'] ?? null,
                'visibility' => $validated['visibility'],
                'avatar_url' => $validated['avatar_url'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
                'conversation_id' => $conversation->id,
                'organization_id' => $validated['organization_id'] ?? null,
                'created_by' => auth()->id(),
            ]);

            Participant::create([
                'conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'role' => 'owner',
                'joined_at' => now(),
            ]);

            $symmetricKey = $this->encryptionService->generateSymmetricKey();
            if ($symmetricKey) {
                $userKeyPair = $this->getUserKeyPair(auth()->id());
                $userDevice = \App\Models\UserDevice::where('user_id', auth()->id())
                    ->where('is_trusted', true)
                    ->first();

                if ($userDevice) {
                    EncryptionKey::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => auth()->id(),
                        'device_id' => $userDevice->id,
                        'device_fingerprint' => $userDevice->device_fingerprint,
                        'encrypted_key' => $this->encryptionService->encryptSymmetricKey(
                            $symmetricKey,
                            $userKeyPair['public_key']
                        ),
                        'public_key' => $userKeyPair['public_key'],
                        'key_version' => 1,
                        'algorithm' => $algorithm,
                        'key_strength' => $keyStrength,
                        'is_active' => true,
                    ]);
                }
            }

            return response()->json(
                $channel->load('conversation.participants.user', 'creator'),
                201
            );
        });
    }

    public function show(Channel $channel)
    {
        $this->authorize('view', $channel);

        return response()->json(
            $channel->load([
                'conversation.participants.user:id,name,email',
                'creator:id,name,email',
                'conversation.messages' => function ($query) {
                    $query->with('sender:id,name,email')
                        ->orderBy('created_at', 'desc')
                        ->limit(50);
                },
            ])
        );
    }

    public function update(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
            'avatar_url' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($channel, $validated) {
            $channel->update($validated);
            
            if (isset($validated['name']) || isset($validated['description'])) {
                $channel->conversation->update([
                    'name' => $validated['name'] ?? $channel->conversation->name,
                    'description' => $validated['description'] ?? $channel->conversation->description,
                ]);
            }

            return response()->json($channel->load('conversation', 'creator'));
        });
    }

    public function destroy(Channel $channel)
    {
        $this->authorize('delete', $channel);

        return DB::transaction(function () use ($channel) {
            $channel->delete();
            $channel->conversation->delete();

            return response()->noContent();
        });
    }

    public function join(Channel $channel)
    {
        if ($channel->isPrivate()) {
            return response()->json(['error' => 'Cannot join private channels directly'], 422);
        }

        $existingParticipant = $channel->conversation->participants()
            ->where('user_id', auth()->id())
            ->first();

        if ($existingParticipant && $existingParticipant->isActive()) {
            return response()->json(['error' => 'You are already a member of this channel'], 422);
        }

        return DB::transaction(function () use ($channel, $existingParticipant) {
            if ($existingParticipant) {
                $existingParticipant->update([
                    'left_at' => null,
                    'joined_at' => now(),
                ]);
            } else {
                Participant::create([
                    'conversation_id' => $channel->conversation_id,
                    'user_id' => auth()->id(),
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }

            $encryptionKey = $channel->conversation->encryptionKeys()
                ->where('is_active', true)
                ->first();

            if ($encryptionKey) {
                try {
                    $symmetricKey = $encryptionKey->decryptSymmetricKey(
                        $this->getUserPrivateKey($channel->created_by)
                    );

                    $userKeyPair = $this->getUserKeyPair(auth()->id());
                    $userDevice = \App\Models\UserDevice::where('user_id', auth()->id())
                        ->where('is_trusted', true)
                        ->firstOrFail();

                    EncryptionKey::create([
                        'conversation_id' => $channel->conversation_id,
                        'user_id' => auth()->id(),
                        'device_id' => $userDevice->id,
                        'device_fingerprint' => $userDevice->device_fingerprint,
                        'encrypted_key' => $this->encryptionService->encryptSymmetricKey(
                            $symmetricKey,
                            $userKeyPair['public_key']
                        ),
                        'public_key' => $userKeyPair['public_key'],
                        'key_version' => 1,
                        'algorithm' => $channel->conversation->encryption_algorithm,
                        'key_strength' => $channel->conversation->key_strength,
                        'is_active' => true,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to setup encryption for new channel member', [
                        'channel_id' => $channel->id,
                        'user_id' => auth()->id(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Successfully joined the channel',
                'channel' => $channel->load('conversation.participants.user'),
            ]);
        });
    }

    public function leave(Channel $channel)
    {
        $participant = $channel->conversation->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (!$participant || !$participant->isActive()) {
            return response()->json(['error' => 'You are not a member of this channel'], 404);
        }

        $participant->update(['left_at' => now()]);

        auth()->user()->chatEncryptionKeys()
            ->where('conversation_id', $channel->conversation_id)
            ->update(['is_active' => false]);

        return response()->json(['message' => 'Successfully left the channel']);
    }

    public function inviteUser(Request $request, Channel $channel)
    {
        $this->authorize('invite', $channel);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:sys_users,id',
        ]);

        if ($channel->isPrivate()) {
            return $this->addParticipants($channel, $validated['user_ids']);
        }

        return response()->json([
            'message' => 'Public channels can be joined directly by users',
            'channel_url' => route('channels.show', $channel->slug),
        ]);
    }

    public function getMembers(Channel $channel)
    {
        $this->authorize('view', $channel);

        $members = $channel->activeParticipants()
            ->with('user:id,name,email,avatar')
            ->orderBy('role')
            ->orderBy('joined_at')
            ->get();

        return response()->json($members);
    }

    public function searchChannels(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
            'organization_id' => 'nullable|exists:organizations,id',
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
        ]);

        $query = Channel::query()
            ->active()
            ->where(function ($q) use ($validated) {
                $q->where('name', 'ilike', '%' . $validated['query'] . '%')
                  ->orWhere('description', 'ilike', '%' . $validated['query'] . '%');
            });

        if (isset($validated['organization_id'])) {
            $query->forOrganization($validated['organization_id']);
        }

        if (isset($validated['visibility'])) {
            $query->where('visibility', $validated['visibility']);
        } else {
            $query->public();
        }

        $channels = $query->with('creator:id,name,email')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($channels);
    }

    private function addParticipants(Channel $channel, array $userIds)
    {
        return DB::transaction(function () use ($channel, $userIds) {
            $addedParticipants = [];
            $errors = [];

            foreach ($userIds as $userId) {
                try {
                    $existingParticipant = $channel->conversation->participants()
                        ->where('user_id', $userId)
                        ->first();

                    if ($existingParticipant && $existingParticipant->isActive()) {
                        $errors[] = "User {$userId} is already a member";
                        continue;
                    }

                    if ($existingParticipant) {
                        $existingParticipant->update([
                            'left_at' => null,
                            'joined_at' => now(),
                        ]);
                        $participant = $existingParticipant;
                    } else {
                        $participant = Participant::create([
                            'conversation_id' => $channel->conversation_id,
                            'user_id' => $userId,
                            'role' => 'member',
                            'joined_at' => now(),
                        ]);
                    }

                    $encryptionKey = $channel->conversation->encryptionKeys()
                        ->where('is_active', true)
                        ->first();

                    if ($encryptionKey) {
                        try {
                            $symmetricKey = $encryptionKey->decryptSymmetricKey(
                                $this->getUserPrivateKey(auth()->id())
                            );

                            $userKeyPair = $this->getUserKeyPair($userId);
                            $userDevice = \App\Models\UserDevice::where('user_id', $userId)
                                ->where('is_trusted', true)
                                ->firstOrFail();

                            EncryptionKey::create([
                                'conversation_id' => $channel->conversation_id,
                                'user_id' => $userId,
                                'device_id' => $userDevice->id,
                                'device_fingerprint' => $userDevice->device_fingerprint,
                                'encrypted_key' => $this->encryptionService->encryptSymmetricKey(
                                    $symmetricKey,
                                    $userKeyPair['public_key']
                                ),
                                'public_key' => $userKeyPair['public_key'],
                                'key_version' => 1,
                                'algorithm' => $channel->conversation->encryption_algorithm,
                                'key_strength' => $channel->conversation->key_strength,
                                'is_active' => true,
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('Failed to setup encryption for new channel participant', [
                                'channel_id' => $channel->id,
                                'user_id' => $userId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $addedParticipants[] = $participant->load('user');
                } catch (\Exception $e) {
                    $errors[] = "Failed to add user {$userId}: " . $e->getMessage();
                }
            }

            $response = [
                'message' => count($addedParticipants) > 0 ? 'Participants added successfully' : 'No participants were added',
                'participants' => $channel->activeParticipants()->with('user:id,name,email,avatar')->get(),
                'added_count' => count($addedParticipants),
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response);
        });
    }

    private function getUserKeyPair(string $userId): array
    {
        $user = User::find($userId);
        if ($user && $user->public_key) {
            $cacheKey = 'user_private_key_' . $userId;
            $encryptedPrivateKey = cache()->get($cacheKey);

            if ($encryptedPrivateKey) {
                try {
                    $privateKey = $this->encryptionService->decryptFromStorage($encryptedPrivateKey);

                    return [
                        'public_key' => $user->public_key,
                        'private_key' => $privateKey,
                    ];
                } catch (\Exception $e) {
                    Log::warning('Failed to decrypt private key from cache', [
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $keyPair = $this->encryptionService->generateKeyPair();
        $cacheKey = 'user_private_key_' . $userId;
        $encryptedPrivateKey = $this->encryptionService->encryptForStorage($keyPair['private_key']);
        cache()->put($cacheKey, $encryptedPrivateKey, now()->addHours(24));

        if ($user && !$user->public_key) {
            $user->update(['public_key' => $keyPair['public_key']]);
        }

        return $keyPair;
    }

    private function getUserPrivateKey(string $userId): string
    {
        $cacheKey = 'user_private_key_' . $userId;
        $encryptedPrivateKey = cache()->get($cacheKey);

        if ($encryptedPrivateKey) {
            try {
                return $this->encryptionService->decryptFromStorage($encryptedPrivateKey);
            } catch (\Exception $e) {
                Log::warning('Failed to decrypt private key from cache', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $keyPair = $this->encryptionService->generateKeyPair();
            $encryptedPrivateKey = $this->encryptionService->encryptForStorage($keyPair['private_key']);
            cache()->put($cacheKey, $encryptedPrivateKey, now()->addHours(24));

            $user = User::find($userId);
            if ($user && !$user->public_key) {
                $user->update(['public_key' => $keyPair['public_key']]);
            }

            return $keyPair['private_key'];
        } catch (\Exception $e) {
            Log::error('Failed to generate fallback private key', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Unable to obtain private key for user');
        }
    }
}