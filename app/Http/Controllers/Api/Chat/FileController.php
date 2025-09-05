<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\MessageFile;
use App\Models\User;
use App\Services\EncryptedFileService;
use App\Services\SignalProtocolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FileController extends Controller
{
    public function __construct(
        private readonly EncryptedFileService $encryptedFileService,
        private readonly SignalProtocolService $signalService
    ) {}

    public function upload(Request $request, string $conversationId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:100000', // 100MB max
                'device_id' => 'required|string',
                'message_content' => 'nullable|string',
                'generate_thumbnail' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $file = $request->file('file');
            $deviceId = $request->input('device_id');
            $messageContent = $request->input('message_content', '');
            $generateThumbnail = $request->boolean('generate_thumbnail', false);

            DB::beginTransaction();

            // Upload and encrypt file
            $fileResult = $this->encryptedFileService->uploadEncryptedFile(
                $file,
                $user,
                $conversation,
                $deviceId,
                [
                    'generate_thumbnail' => $generateThumbnail,
                    'message_content' => $messageContent,
                ]
            );

            // Create message with file attachment
            $encryptionResult = $this->signalService->encryptMessage(
                $user,
                $conversation,
                $messageContent ?: "📎 {$file->getClientOriginalName()}",
                $deviceId,
                ['message_type' => 'file']
            );

            $message = Message::create([
                'id' => $encryptionResult['message_id'],
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'device_id' => $deviceId,
                'message_type' => 'file',
                'encrypted_content' => $encryptionResult['encrypted_content'],
                'content_hash' => $encryptionResult['content_hash'],
                'encryption_version' => $encryptionResult['encryption_version'],
                'quantum_resistant' => $encryptionResult['quantum_resistant'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create file attachment record
            $messageFile = MessageFile::create([
                'id' => $fileResult['file_id'],
                'message_id' => $message->id,
                'original_filename' => $file->getClientOriginalName(),
                'encrypted_filename' => $fileResult['encrypted_filename'],
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'encrypted_size' => $fileResult['encrypted_size'],
                'file_hash' => $fileResult['file_hash'],
                'encryption_key_encrypted' => $fileResult['encryption_keys'],
                'thumbnail_path' => $fileResult['thumbnail_path'] ?? null,
                'thumbnail_encrypted' => $fileResult['thumbnail_encrypted'] ?? false,
                'metadata' => json_encode($fileResult['metadata']),
            ]);

            DB::commit();

            Log::info('File uploaded and encrypted', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'file_id' => $messageFile->id,
                'file_size' => $file->getSize(),
                'quantum_resistant' => $encryptionResult['quantum_resistant'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id' => $message->sender_id,
                    'message_type' => $message->message_type,
                    'encrypted_content' => $message->encrypted_content,
                    'content_hash' => $message->content_hash,
                    'encryption_version' => $message->encryption_version,
                    'quantum_resistant' => $message->quantum_resistant,
                    'created_at' => $message->created_at,
                    'file' => [
                        'id' => $messageFile->id,
                        'original_filename' => $messageFile->original_filename,
                        'mime_type' => $messageFile->mime_type,
                        'file_size' => $messageFile->file_size,
                        'has_thumbnail' => ! is_null($messageFile->thumbnail_path),
                    ],
                ],
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('File upload failed', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'File upload failed',
            ], 500);
        }
    }

    public function download(Request $request, string $conversationId, string $fileId): Response
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);
            $deviceId = $request->input('device_id');

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $messageFile = MessageFile::where('id', $fileId)
                ->whereHas('message', function ($query) use ($conversationId) {
                    $query->where('conversation_id', $conversationId);
                })
                ->firstOrFail();

            // Download and decrypt file
            $decryptedFile = $this->encryptedFileService->downloadDecryptedFile(
                $messageFile,
                $user,
                $deviceId
            );

            Log::info('File downloaded and decrypted', [
                'user_id' => $user->id,
                'file_id' => $fileId,
                'conversation_id' => $conversationId,
            ]);

            return response()->stream(function () use ($decryptedFile) {
                echo $decryptedFile['content'];
            }, 200, [
                'Content-Type' => $messageFile->mime_type,
                'Content-Disposition' => 'attachment; filename="'.$messageFile->original_filename.'"',
                'Content-Length' => (string) $messageFile->file_size,
                'X-File-Hash' => $decryptedFile['file_hash'],
                'X-Integrity-Verified' => $decryptedFile['integrity_verified'] ? '1' : '0',
            ]);

        } catch (\Exception $e) {
            Log::error('File download failed', [
                'user_id' => Auth::id(),
                'file_id' => $fileId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'File download failed',
            ], 500);
        }
    }

    public function thumbnail(Request $request, string $conversationId, string $fileId): Response
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);
            $deviceId = $request->input('device_id');

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $messageFile = MessageFile::where('id', $fileId)
                ->whereHas('message', function ($query) use ($conversationId) {
                    $query->where('conversation_id', $conversationId);
                })
                ->whereNotNull('thumbnail_path')
                ->firstOrFail();

            // Get thumbnail
            $thumbnail = $this->encryptedFileService->getThumbnail(
                $messageFile,
                $user,
                $deviceId
            );

            if (! $thumbnail) {
                return response()->json([
                    'success' => false,
                    'error' => 'Thumbnail not available',
                ], 404);
            }

            return response()->stream(function () use ($thumbnail) {
                echo $thumbnail['content'];
            }, 200, [
                'Content-Type' => $thumbnail['mime_type'],
                'Content-Length' => (string) strlen($thumbnail['content']),
                'Cache-Control' => 'private, max-age=3600',
            ]);

        } catch (\Exception $e) {
            Log::error('Thumbnail download failed', [
                'user_id' => Auth::id(),
                'file_id' => $fileId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Thumbnail not available',
            ], 404);
        }
    }

    public function bulkUpload(Request $request, string $conversationId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'files' => 'required|array|max:10', // Max 10 files
                'files.*' => 'required|file|max:100000',
                'device_id' => 'required|string',
                'message_content' => 'nullable|string',
                'generate_thumbnails' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $files = $request->file('files');
            $deviceId = $request->input('device_id');
            $messageContent = $request->input('message_content', '');
            $generateThumbnails = $request->boolean('generate_thumbnails', false);

            // Process files in bulk
            $results = $this->encryptedFileService->bulkUploadFiles(
                $files,
                $user,
                $conversation,
                $deviceId,
                [
                    'generate_thumbnails' => $generateThumbnails,
                    'message_content' => $messageContent,
                ]
            );

            Log::info('Bulk file upload completed', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'file_count' => count($files),
                'successful_uploads' => count($results['successful']),
                'failed_uploads' => count($results['failed']),
            ]);

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk file upload failed', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Bulk file upload failed',
            ], 500);
        }
    }

    public function getFileInfo(string $conversationId, string $fileId): JsonResponse
    {
        try {
            $user = Auth::user();
            $conversation = Conversation::findOrFail($conversationId);

            // Verify user is participant
            if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access denied',
                ], 403);
            }

            $messageFile = MessageFile::where('id', $fileId)
                ->whereHas('message', function ($query) use ($conversationId) {
                    $query->where('conversation_id', $conversationId);
                })
                ->with('message')
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'file' => [
                    'id' => $messageFile->id,
                    'message_id' => $messageFile->message_id,
                    'original_filename' => $messageFile->original_filename,
                    'mime_type' => $messageFile->mime_type,
                    'file_size' => $messageFile->file_size,
                    'encrypted_size' => $messageFile->encrypted_size,
                    'file_hash' => $messageFile->file_hash,
                    'has_thumbnail' => ! is_null($messageFile->thumbnail_path),
                    'metadata' => json_decode($messageFile->metadata, true),
                    'created_at' => $messageFile->created_at,
                    'sender' => [
                        'id' => $messageFile->message->sender_id,
                        'name' => $messageFile->message->sender->name,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get file info failed', [
                'user_id' => Auth::id(),
                'file_id' => $fileId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'File not found',
            ], 404);
        }
    }
}
