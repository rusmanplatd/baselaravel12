<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use App\Models\Chat\MessageFile;
use App\Models\User;
use App\Services\E2EEFileService;
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
        private readonly E2EEFileService $e2eeFileService,
        private readonly SignalProtocolService $signalService
    ) {}

    public function upload(Request $request, string $conversationId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'encrypted_file' => 'required|file|max:100000', // 100MB max encrypted file
                'device_id' => 'required|string',
                'original_filename' => 'required|string',
                'original_mime_type' => 'required|string',
                'original_size' => 'required|integer',
                'file_hash' => 'required|string',
                'encryption_key_data' => 'required|array',
                'message_content' => 'nullable|string',
                'encrypted_thumbnail' => 'nullable|string', // Base64 encoded encrypted thumbnail
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

            $encryptedFile = $request->file('encrypted_file');
            $deviceId = $request->input('device_id');
            $messageContent = $request->input('message_content', '');
            
            // Collect metadata from the request
            $metadata = [
                'original_filename' => $request->input('original_filename'),
                'original_mime_type' => $request->input('original_mime_type'),
                'original_size' => (int)$request->input('original_size'),
                'file_hash' => $request->input('file_hash'),
                'encryption_key_data' => $request->input('encryption_key_data'),
                'encrypted_thumbnail' => $request->input('encrypted_thumbnail'),
            ];

            DB::beginTransaction();

            // Store the encrypted file blob
            $fileResult = $this->e2eeFileService->storeEncryptedFile(
                $encryptedFile,
                $user,
                $conversation,
                $deviceId,
                $metadata
            );

            // Create message with file attachment
            $encryptionResult = $this->signalService->encryptMessage(
                $user,
                $conversation,
                $messageContent ?: "📎 {$metadata['original_filename']}",
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
                'original_filename' => $fileResult['original_filename'],
                'encrypted_filename' => $fileResult['encrypted_filename'],
                'mime_type' => $fileResult['original_mime_type'],
                'file_size' => $fileResult['original_size'],
                'encrypted_size' => $fileResult['encrypted_size'],
                'file_hash' => $fileResult['file_hash'],
                'encryption_key_encrypted' => $fileResult['encryption_key_data'],
                'thumbnail_path' => $fileResult['thumbnail_path'],
                'thumbnail_encrypted' => !is_null($fileResult['thumbnail_path']),
                'metadata' => json_encode($fileResult['metadata']),
            ]);

            DB::commit();

            Log::info('Encrypted file stored successfully', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'file_id' => $messageFile->id,
                'original_filename' => $fileResult['original_filename'],
                'original_size' => $fileResult['original_size'],
                'encrypted_size' => $fileResult['encrypted_size'],
                'has_thumbnail' => !is_null($fileResult['thumbnail_path']),
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
                        'encrypted_size' => $messageFile->encrypted_size,
                        'file_type' => E2EEFileService::getFileTypeCategory($messageFile->mime_type),
                        'has_thumbnail' => !is_null($messageFile->thumbnail_path),
                        'supports_preview' => $fileResult['metadata']['supports_preview'],
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
            Log::error('Encrypted file upload failed', [
                'user_id' => Auth::id(),
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'File upload failed: ' . $e->getMessage(),
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

            // Retrieve encrypted file blob
            $encryptedFileData = $this->e2eeFileService->retrieveEncryptedFile(
                $messageFile,
                $user,
                $deviceId
            );

            Log::info('Encrypted file retrieved for client decryption', [
                'user_id' => $user->id,
                'file_id' => $fileId,
                'conversation_id' => $conversationId,
                'encrypted_size' => strlen($encryptedFileData['encrypted_content']),
            ]);

            // Return encrypted blob for client-side decryption
            return response()->stream(function () use ($encryptedFileData) {
                echo $encryptedFileData['encrypted_content'];
            }, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.$messageFile->id.'.encrypted"',
                'Content-Length' => (string) $encryptedFileData['encrypted_size'],
                'X-Original-Filename' => $encryptedFileData['original_filename'],
                'X-Original-Mime-Type' => $encryptedFileData['original_mime_type'],
                'X-Original-Size' => (string) $encryptedFileData['original_size'],
                'X-File-Hash' => $encryptedFileData['file_hash'],
                'X-Encryption-Key-Data' => base64_encode(json_encode($encryptedFileData['encryption_key_data'])),
                'X-File-Id' => $messageFile->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Encrypted file download failed', [
                'user_id' => Auth::id(),
                'file_id' => $fileId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'File download failed: ' . $e->getMessage(),
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

            // Get encrypted thumbnail
            $encryptedThumbnail = $this->e2eeFileService->retrieveEncryptedThumbnail(
                $messageFile,
                $user,
                $deviceId
            );

            if (!$encryptedThumbnail) {
                return response()->json([
                    'success' => false,
                    'error' => 'Thumbnail not available',
                ], 404);
            }

            // Return encrypted thumbnail for client-side decryption
            return response()->stream(function () use ($encryptedThumbnail) {
                echo $encryptedThumbnail['encrypted_content'];
            }, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => (string) $encryptedThumbnail['size'],
                'Cache-Control' => 'private, max-age=3600',
                'X-File-Id' => $messageFile->id,
                'X-Is-Thumbnail' => '1',
            ]);

        } catch (\Exception $e) {
            Log::error('Encrypted thumbnail download failed', [
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
