<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Participant;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\ChatEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function __construct(private ChatEncryptionService $encryptionService)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $conversations = auth()->user()
            ->activeConversations()
            ->with(['participants.user:id,name,email', 'messages' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return response()->json($conversations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['direct', 'group'])],
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'participants' => 'required|array|min:1',
            'participants.*' => 'required|string',
        ]);

        if ($validated['type'] === 'direct' && count($validated['participants']) !== 1) {
            return response()->json(['error' => 'Direct conversations must have exactly one other participant'], 422);
        }

        // Convert emails/IDs to user IDs
        $participantIds = collect($validated['participants'])->map(function ($identifier) {
            // If it's a ULID (26 chars, base32) or UUID, use it directly
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier) ||
                preg_match('/^[0-9a-hjkmnp-tv-z]{26}$/i', $identifier)) {
                $user = User::find($identifier);
            } else {
                // Otherwise, treat it as an email
                $user = User::where('email', $identifier)->first();
            }

            if (! $user) {
                abort(422, "User not found: {$identifier}");
            }

            return $user->id;
        });

        $participants = $participantIds
            ->push(auth()->id())
            ->unique()
            ->values();

        if ($validated['type'] === 'direct') {
            $existingConversation = Conversation::where('type', 'direct')
                ->whereHas('participants', function ($query) use ($participants) {
                    $query->whereIn('user_id', $participants)
                        ->whereNull('left_at');
                }, '=', $participants->count())
                ->first();

            if ($existingConversation) {
                return response()->json($existingConversation->load('participants.user'));
            }
        }

        return DB::transaction(function () use ($validated, $participants) {
            $conversation = Conversation::create([
                'name' => $validated['name'] ?? null,
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $symmetricKey = $this->encryptionService->generateSymmetricKey();

            foreach ($participants as $index => $userId) {
                $role = $userId === auth()->id() ? 'owner' : 'member';

                Participant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'role' => $role,
                    'joined_at' => now(),
                ]);

                $userKeyPair = $this->getUserKeyPair($userId);

                // Get user's device for key creation (use primary device)
                $userDevice = \App\Models\UserDevice::where('user_id', $userId)
                    ->where('is_trusted', true)
                    ->first();

                if ($userDevice) {
                    EncryptionKey::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'device_id' => $userDevice->id,
                        'device_fingerprint' => $userDevice->device_fingerprint,
                        'encrypted_key' => $this->encryptionService->encryptSymmetricKey(
                            $symmetricKey,
                            $userKeyPair['public_key']
                        ),
                        'key_version' => 1,
                        'algorithm' => 'RSA-OAEP',
                        'key_strength' => 4096,
                        'is_active' => true,
                    ]);
                }
            }

            return response()->json(
                $conversation->load('participants.user'),
                201
            );
        });
    }

    public function show(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        return response()->json(
            $conversation->load([
                'participants.user:id,name,email',
                'messages' => function ($query) {
                    $query->with('sender:id,name,email')
                        ->orderBy('created_at', 'desc')
                        ->limit(50);
                },
            ])
        );
    }

    public function update(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $conversation->update($validated);

        return response()->json($conversation);
    }

    public function destroy(Conversation $conversation)
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted successfully']);
    }

    public function addParticipant(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'user_ids' => 'required_without:user_id|array',
            'user_ids.*' => 'exists:sys_users,id',
            'user_id' => 'required_without:user_ids|exists:sys_users,id', // For backward compatibility
        ]);

        if ($conversation->type === 'direct') {
            return response()->json(['error' => 'Cannot add participants to direct conversations'], 422);
        }

        // Handle both single user_id and multiple user_ids for backward compatibility
        $userIds = $validated['user_ids'] ?? [$validated['user_id']];

        return DB::transaction(function () use ($conversation, $userIds) {
            $addedParticipants = [];
            $errors = [];

            foreach ($userIds as $userId) {
                try {
                    $existingParticipant = $conversation->participants()
                        ->where('user_id', $userId)
                        ->first();

                    if ($existingParticipant && $existingParticipant->isActive()) {
                        $errors[] = "User {$userId} is already a participant";
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
                            'conversation_id' => $conversation->id,
                            'user_id' => $userId,
                            'role' => 'member',
                            'joined_at' => now(),
                        ]);
                    }

                    // Set up encryption key if conversation has encryption enabled
                    $encryptionKey = $conversation->encryptionKeys()
                        ->where('is_active', true)
                        ->first();

                    if ($encryptionKey) {
                        try {
                            $symmetricKey = $encryptionKey->decryptSymmetricKey(
                                $this->getUserPrivateKey(auth()->id())
                            );

                            $userKeyPair = $this->getUserKeyPair($userId);

                            EncryptionKey::createForUser(
                                $conversation->id,
                                $userId,
                                $symmetricKey,
                                $userKeyPair['public_key']
                            );
                        } catch (\Exception $e) {
                            // Log encryption setup error but don't fail the participant addition
                            Log::warning('Failed to setup encryption for new participant', [
                                'conversation_id' => $conversation->id,
                                'user_id' => $userId,
                                'error' => $e->getMessage()
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
                'participants' => $conversation->activeParticipants()->with('user:id,name,email,avatar')->get(),
                'added_count' => count($addedParticipants),
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return response()->json($response);
        });
    }

    public function removeParticipant(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'user_id' => 'required|exists:sys_users,id',
        ]);

        $participant = $conversation->participants()
            ->where('user_id', $validated['user_id'])
            ->first();

        if (! $participant || ! $participant->isActive()) {
            return response()->json(['error' => 'User is not an active participant'], 404);
        }

        $participant->leave();

        $participant->user->chatEncryptionKeys()
            ->where('conversation_id', $conversation->id)
            ->update(['is_active' => false]);

        return response()->json(['message' => 'Participant removed successfully']);
    }

    public function removeParticipantById(Conversation $conversation, User $user)
    {
        $this->authorize('update', $conversation);

        $participant = $conversation->participants()
            ->where('user_id', $user->id)
            ->first();

        if (! $participant || ! $participant->isActive()) {
            return response()->json(['error' => 'User is not an active participant'], 404);
        }

        $participant->leave();

        // Deactivate encryption keys for this user in this conversation
        $user->chatEncryptionKeys()
            ->where('conversation_id', $conversation->id)
            ->update(['is_active' => false]);

        return response('', 204); // No content response as expected by test
    }

    public function updateParticipantRole(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        if ($conversation->type !== 'group') {
            return response()->json(['error' => 'Cannot manage roles in direct conversations'], 422);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:sys_users,id',
            'role' => ['required', Rule::in(['member', 'admin'])],
        ]);

        $currentParticipant = $conversation->participants()
            ->where('user_id', auth()->id())
            ->first();

        if (! in_array($currentParticipant->role, ['owner', 'admin'])) {
            return response()->json(['error' => 'Only group owners and admins can manage roles'], 403);
        }

        $participant = $conversation->participants()
            ->where('user_id', $validated['user_id'])
            ->first();

        if (! $participant || ! $participant->isActive()) {
            return response()->json(['error' => 'User is not an active participant'], 404);
        }

        if ($participant->isOwner()) {
            return response()->json(['error' => 'Cannot change owner role'], 422);
        }

        $participant->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'Participant role updated successfully',
            'participant' => $participant->load('user'),
        ]);
    }

    public function getParticipants(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $participants = $conversation->participants()
            ->with('user:id,name,email,avatar')
            ->whereNull('left_at')
            ->orderBy('role')
            ->orderBy('joined_at')
            ->get();

        return response()->json($participants);
    }

    public function updateGroupSettings(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        if ($conversation->type !== 'group') {
            return response()->json(['error' => 'Cannot update settings for direct conversations'], 422);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'avatar_url' => 'nullable|url',
            'settings' => 'nullable|array',
            'settings.allow_member_invite' => 'nullable|boolean',
            'settings.only_admins_can_message' => 'nullable|boolean',
            'settings.message_retention_days' => 'nullable|integer|min:1|max:365',
        ]);

        $metadata = $conversation->metadata ?? [];
        if (isset($validated['settings'])) {
            $metadata['settings'] = array_merge($metadata['settings'] ?? [], $validated['settings']);
            unset($validated['settings']);
        }

        $validated['metadata'] = $metadata;

        $conversation->update($validated);

        return response()->json([
            'message' => 'Group settings updated successfully',
            'conversation' => $conversation,
        ]);
    }

    public function generateInviteLink(Request $request, Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        if ($conversation->type !== 'group') {
            return response()->json(['error' => 'Invite links only available for groups'], 422);
        }

        $validated = $request->validate([
            'expires_at' => 'nullable|date|after:now',
            'max_uses' => 'nullable|integer|min:1|max:100',
        ]);

        $inviteCode = bin2hex(random_bytes(16));

        $metadata = $conversation->metadata ?? [];
        $metadata['invite_links'][] = [
            'code' => $inviteCode,
            'created_by' => auth()->id(),
            'created_at' => now()->toISOString(),
            'expires_at' => $validated['expires_at'] ?? null,
            'max_uses' => $validated['max_uses'] ?? null,
            'current_uses' => 0,
            'is_active' => true,
        ];

        $conversation->update(['metadata' => $metadata]);

        $inviteUrl = url("/chat/join/{$inviteCode}");

        return response()->json([
            'invite_url' => $inviteUrl,
            'invite_code' => $inviteCode,
            'expires_at' => $validated['expires_at'] ?? null,
            'max_uses' => $validated['max_uses'] ?? null,
        ]);
    }

    public function joinByInvite(Request $request, string $inviteCode)
    {
        $validated = $request->validate([
            'invite_code' => 'required|string',
        ]);

        $conversation = Conversation::where('type', 'group')
            ->where('metadata->invite_links', 'like', "%{$inviteCode}%")
            ->first();

        if (! $conversation) {
            return response()->json(['error' => 'Invalid or expired invite link'], 404);
        }

        $metadata = $conversation->metadata ?? [];
        $inviteLinks = $metadata['invite_links'] ?? [];

        $inviteLink = collect($inviteLinks)->firstWhere('code', $inviteCode);

        if (! $inviteLink || ! $inviteLink['is_active']) {
            return response()->json(['error' => 'Invalid or expired invite link'], 404);
        }

        if ($inviteLink['expires_at'] && now()->gt($inviteLink['expires_at'])) {
            return response()->json(['error' => 'Invite link has expired'], 410);
        }

        if ($inviteLink['max_uses'] && $inviteLink['current_uses'] >= $inviteLink['max_uses']) {
            return response()->json(['error' => 'Invite link has reached maximum uses'], 410);
        }

        $existingParticipant = $conversation->participants()
            ->where('user_id', auth()->id())
            ->first();

        if ($existingParticipant && $existingParticipant->isActive()) {
            return response()->json(['error' => 'You are already a member of this group'], 422);
        }

        return DB::transaction(function () use ($conversation, $inviteCode, $existingParticipant, $metadata, $inviteLinks) {
            if ($existingParticipant) {
                $existingParticipant->update([
                    'left_at' => null,
                    'joined_at' => now(),
                ]);
            } else {
                Participant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => auth()->id(),
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }

            $encryptionKey = $conversation->encryptionKeys()
                ->where('is_active', true)
                ->first();

            if ($encryptionKey) {
                $symmetricKey = $encryptionKey->decryptSymmetricKey(
                    $this->getUserPrivateKey($conversation->created_by)
                );

                $userKeyPair = $this->getUserKeyPair(auth()->id());

                EncryptionKey::createForUser(
                    $conversation->id,
                    auth()->id(),
                    $symmetricKey,
                    $userKeyPair['public_key']
                );
            }

            // Update invite link usage
            foreach ($inviteLinks as &$link) {
                if ($link['code'] === $inviteCode) {
                    $link['current_uses']++;
                    break;
                }
            }
            $metadata['invite_links'] = $inviteLinks;
            $conversation->update(['metadata' => $metadata]);

            return response()->json([
                'message' => 'Successfully joined the group',
                'conversation' => $conversation->load('participants.user'),
            ]);
        });
    }

    private function getUserKeyPair(string $userId): array
    {
        // Check if user has a public key already
        $user = User::find($userId);
        if ($user && $user->public_key) {
            // Try to get private key from cache
            $cacheKey = 'user_private_key_'.$userId;
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

        // Generate new key pair
        $keyPair = $this->encryptionService->generateKeyPair();

        // Cache the private key
        $cacheKey = 'user_private_key_'.$userId;
        $encryptedPrivateKey = $this->encryptionService->encryptForStorage($keyPair['private_key']);
        cache()->put($cacheKey, $encryptedPrivateKey, now()->addHours(24));

        // Update user's public key if not set
        if ($user && ! $user->public_key) {
            $user->update(['public_key' => $keyPair['public_key']]);
        }

        return $keyPair;
    }

    private function getUserPrivateKey(string $userId): string
    {
        // Try to get private key from cache (set during key generation)
        $cacheKey = 'user_private_key_'.$userId;
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

        // Fallback: Generate a temporary key pair and cache it
        try {
            $keyPair = $this->encryptionService->generateKeyPair();
            $encryptedPrivateKey = $this->encryptionService->encryptForStorage($keyPair['private_key']);
            cache()->put($cacheKey, $encryptedPrivateKey, now()->addHours(24));

            // Also update user's public key if not set
            $user = User::find($userId);
            if ($user && ! $user->public_key) {
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
