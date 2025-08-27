<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Services\ChatFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    public function __construct(private ChatFileService $fileService)
    {
        $this->middleware('auth:api');
    }

    public function upload(Request $request, Conversation $conversation)
    {
        $this->authorize('participate', $conversation);

        $validated = $request->validate([
            'file' => 'required|file|max:102400', // 100MB
            'caption' => 'nullable|string|max:1000',
        ]);

        $encryptionKey = auth()->user()
            ->getActiveEncryptionKeyForConversation($conversation->id);

        if (! $encryptionKey) {
            return response()->json(['error' => 'Unable to encrypt file'], 403);
        }

        return DB::transaction(function () use ($conversation, $validated, $encryptionKey) {
            $symmetricKey = $encryptionKey->decryptSymmetricKey(
                $this->getUserPrivateKey(auth()->id())
            );

            // Store the encrypted file
            $fileData = $this->fileService->storeFile(
                $validated['file'],
                $symmetricKey
            );

            // Create message with file data
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => auth()->id(),
                'type' => $this->getMessageType($fileData['mime_type']),
                'encrypted_content' => json_encode([
                    'data' => base64_encode($validated['caption'] ?? ''),
                    'iv' => base64_encode(random_bytes(16)),
                ]),
                'content_hash' => hash('sha256', $validated['caption'] ?? ''),
                'file_path' => $fileData['file_path'],
                'file_name' => $fileData['file_name'],
                'file_mime_type' => $fileData['mime_type'],
                'file_size' => $fileData['file_size'],
            ]);

            $conversation->update(['last_message_at' => now()]);

            $message->load('sender:id,name,email');
            $message->content = $validated['caption'] ?? '';
            $message->file_url = $this->fileService->getFileUrl($fileData['file_path']);

            return response()->json($message, 201);
        });
    }

    public function download(Request $request, string $encodedPath)
    {
        // Check for token-based access first
        $token = $request->input('token');
        $expires = $request->input('expires');

        if ($token && $expires) {
            return $this->downloadWithToken($request, $encodedPath, $token, $expires);
        }

        // Fallback to auth-based access
        $filePath = base64_decode($encodedPath);

        // Find the message to check permissions
        $message = Message::where('file_path', $filePath)->firstOrFail();
        $this->authorize('view', $message->conversation);

        $encryptionKey = auth()->user()
            ->getActiveEncryptionKeyForConversation($message->conversation_id);

        if (! $encryptionKey) {
            return response()->json(['error' => 'Unable to decrypt file'], 403);
        }

        $symmetricKey = $encryptionKey->decryptSymmetricKey(
            $this->getUserPrivateKey(auth()->id())
        );

        $fileData = $this->fileService->retrieveFile($filePath, $symmetricKey);

        return response($fileData['contents'])
            ->header('Content-Type', $message->file_mime_type)
            ->header('Content-Disposition', 'attachment; filename="'.$message->file_name.'"')
            ->header('Content-Length', $message->file_size);
    }

    private function downloadWithToken(Request $request, string $encodedPath, string $token, string $expires)
    {
        // Check if token is expired
        if (time() > (int) $expires) {
            return response()->json(['error' => 'Download token expired'], 403);
        }

        $filePath = base64_decode($encodedPath);

        // Generate expected token
        $expectedToken = hash_hmac('sha256', $filePath.$expires, config('app.key'));

        // Check if token is valid
        if (! hash_equals($expectedToken, $token)) {
            return response()->json(['error' => 'Invalid download token'], 403);
        }

        // Find the message
        $message = Message::where('file_path', $filePath)->firstOrFail();

        // For token-based downloads, we'll use a default symmetric key or retrieve from message
        // TODO: In a real implementation, the key would be embedded in the secure token
        try {
            // Try to get file contents directly for token-based access
            // This is a simplified approach - in production you'd embed encryption info in the token
            $fileData = $this->fileService->retrieveFile($filePath, 'dummy-key');

            return response($fileData['contents'])
                ->header('Content-Type', $message->file_mime_type)
                ->header('Content-Disposition', 'attachment; filename="'.$message->file_name.'"')
                ->header('Content-Length', $message->file_size);
        } catch (\Exception $e) {
            // If decryption fails, return error
            return response()->json(['error' => 'Unable to decrypt file'], 500);
        }
    }

    public function delete(Message $message)
    {
        $this->authorize('delete', $message);

        if (! $message->file_path) {
            return response()->json(['error' => 'Message has no associated file'], 404);
        }

        $this->fileService->deleteFile($message->file_path);
        $message->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

    private function getMessageType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        return 'file';
    }

    private function getUserPrivateKey(string $userId): string
    {
        // Try to get private key from cache (set during key generation)
        $cacheKey = 'user_private_key_'.$userId;
        $encryptedPrivateKey = cache()->get($cacheKey);

        if ($encryptedPrivateKey) {
            try {
                return app(\App\Services\ChatEncryptionService::class)->decryptFromStorage($encryptedPrivateKey);
            } catch (\Exception $e) {
                Log::warning('Failed to decrypt private key from cache', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: Generate a temporary key pair and cache it
        try {
            $encryptionService = app(\App\Services\ChatEncryptionService::class);
            $keyPair = $encryptionService->generateKeyPair();
            $encryptedPrivateKey = $encryptionService->encryptForStorage($keyPair['private_key']);
            cache()->put($cacheKey, $encryptedPrivateKey, now()->addHours(24));

            // Also update user's public key if not set
            $user = \App\Models\User::find($userId);
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
