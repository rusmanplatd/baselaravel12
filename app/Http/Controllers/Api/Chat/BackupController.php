<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\ChatBackup;
use App\Models\Chat\Conversation;
use App\Services\EncryptedBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;

class BackupController extends Controller
{
    public function __construct(
        private readonly EncryptedBackupService $backupService
    ) {}

    public function create(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string',
                'backup_type' => 'required|in:full_account,conversation,date_range',
                'conversation_id' => 'required_if:backup_type,conversation|exists:chat_conversations,id',
                'export_format' => 'in:json,xml,pdf,html',
                'backup_scope' => 'array',
                'backup_scope.*' => 'in:messages,files,polls,surveys,reactions',
                'date_range' => 'required_if:backup_type,date_range|array',
                'date_range.start' => 'required_with:date_range|date',
                'date_range.end' => 'required_with:date_range|date|after:date_range.start',
                'include_attachments' => 'boolean',
                'include_metadata' => 'boolean',
                'preserve_encryption' => 'boolean',
                'retention_days' => 'integer|min:1|max:365',
                'encryption_algorithm' => 'in:aes-256-gcm,aes-256-cbc',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $deviceId = $request->input('device_id');

            // Verify conversation access if specified
            if ($request->has('conversation_id')) {
                $conversation = Conversation::findOrFail($request->input('conversation_id'));
                if (! $conversation->participants()->where('user_id', $user->id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Access denied to conversation',
                    ], 403);
                }
            }

            // Check for existing pending/processing backups
            $existingBackup = ChatBackup::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'processing'])
                ->first();

            if ($existingBackup) {
                return response()->json([
                    'success' => false,
                    'error' => 'Another backup is already in progress',
                ], 409);
            }

            DB::beginTransaction();

            $backup = $this->backupService->createBackup($user, $request->all(), $deviceId);

            // Queue backup processing
            Queue::push('ProcessBackupJob', [
                'backup_id' => $backup->id,
            ]);

            DB::commit();

            Log::info('Backup job queued', [
                'backup_id' => $backup->id,
                'user_id' => $user->id,
                'backup_type' => $backup->backup_type,
            ]);

            return response()->json([
                'success' => true,
                'backup' => [
                    'id' => $backup->id,
                    'backup_type' => $backup->backup_type,
                    'export_format' => $backup->export_format,
                    'status' => $backup->status,
                    'created_at' => $backup->created_at,
                    'expires_at' => $backup->expires_at,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Backup creation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create backup',
            ], 500);
        }
    }

    public function list(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $backups = ChatBackup::where('user_id', $user->id)
                ->with(['conversation'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $backupsData = $backups->items();
            $formattedBackups = array_map(function ($backup) {
                return [
                    'id' => $backup->id,
                    'backup_type' => $backup->backup_type,
                    'export_format' => $backup->export_format,
                    'status' => $backup->status,
                    'progress_percentage' => $backup->progress_percentage,
                    'backup_scope' => $backup->backup_scope,
                    'include_attachments' => $backup->include_attachments,
                    'include_metadata' => $backup->include_metadata,
                    'preserve_encryption' => $backup->preserve_encryption,
                    'backup_file_size' => $backup->backup_file_size,
                    'file_size_formatted' => $backup->getFileSizeFormatted(),
                    'duration_formatted' => $backup->getDurationFormatted(),
                    'conversation_name' => $backup->conversation?->name,
                    'created_at' => $backup->created_at,
                    'completed_at' => $backup->completed_at,
                    'expires_at' => $backup->expires_at,
                    'can_download' => $backup->canBeDownloaded(),
                    'is_expired' => $backup->isExpired(),
                ];
            }, $backupsData);

            return response()->json([
                'success' => true,
                'backups' => $formattedBackups,
                'pagination' => [
                    'current_page' => $backups->currentPage(),
                    'per_page' => $backups->perPage(),
                    'total' => $backups->total(),
                    'last_page' => $backups->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to list backups', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve backups',
            ], 500);
        }
    }

    public function show(Request $request, string $backupId): JsonResponse
    {
        try {
            $user = Auth::user();
            $backup = ChatBackup::where('user_id', $user->id)
                ->with(['conversation', 'verification'])
                ->findOrFail($backupId);

            $backupData = [
                'id' => $backup->id,
                'backup_type' => $backup->backup_type,
                'export_format' => $backup->export_format,
                'backup_scope' => $backup->backup_scope,
                'date_range' => $backup->date_range,
                'status' => $backup->status,
                'status_message' => $backup->status_message,
                'progress_percentage' => $backup->progress_percentage,
                'total_items' => $backup->total_items,
                'processed_items' => $backup->processed_items,
                'backup_file_size' => $backup->backup_file_size,
                'file_size_formatted' => $backup->getFileSizeFormatted(),
                'include_attachments' => $backup->include_attachments,
                'include_metadata' => $backup->include_metadata,
                'preserve_encryption' => $backup->preserve_encryption,
                'encryption_settings' => $backup->encryption_settings,
                'conversation_name' => $backup->conversation?->name,
                'duration_formatted' => $backup->getDurationFormatted(),
                'started_at' => $backup->started_at,
                'completed_at' => $backup->completed_at,
                'expires_at' => $backup->expires_at,
                'created_at' => $backup->created_at,
                'is_verified' => $backup->isVerified(),
                'verification' => $backup->verification ? [
                    'method' => $backup->verification->verification_method,
                    'verified_at' => $backup->verification->verified_at,
                    'notes' => $backup->verification->verification_notes,
                ] : null,
                'can_download' => $backup->canBeDownloaded(),
                'can_restore' => $backup->canBeRestored(),
                'is_expired' => $backup->isExpired(),
                'error_log' => $backup->error_log,
            ];

            return response()->json([
                'success' => true,
                'backup' => $backupData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve backup details', [
                'user_id' => Auth::id(),
                'backup_id' => $backupId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Backup not found',
            ], 404);
        }
    }

    public function download(Request $request, string $backupId): Response
    {
        try {
            $user = Auth::user();
            $backup = ChatBackup::where('user_id', $user->id)->findOrFail($backupId);

            if (! $backup->canBeDownloaded()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Backup cannot be downloaded',
                ], 400);
            }

            if (! file_exists($backup->backup_file_path)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Backup file not found',
                ], 404);
            }

            Log::info('Backup downloaded', [
                'backup_id' => $backup->id,
                'user_id' => $user->id,
            ]);

            return response()->download(
                $backup->backup_file_path,
                "chat_backup_{$backup->backup_type}_{$backup->created_at->format('Y-m-d')}.zip",
                [
                    'Content-Type' => 'application/zip',
                    'X-Backup-Hash' => $backup->backup_file_hash,
                    'X-Backup-Size' => (string) $backup->backup_file_size,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Backup download failed', [
                'user_id' => Auth::id(),
                'backup_id' => $backupId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to download backup',
            ], 500);
        }
    }

    public function delete(Request $request, string $backupId): JsonResponse
    {
        try {
            $user = Auth::user();
            $backup = ChatBackup::where('user_id', $user->id)->findOrFail($backupId);

            // Don't allow deletion of processing backups
            if ($backup->isProcessing()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot delete backup while processing',
                ], 400);
            }

            // Delete backup file
            if ($backup->backup_file_path && file_exists($backup->backup_file_path)) {
                unlink($backup->backup_file_path);
            }

            // Delete database record
            $backup->delete();

            Log::info('Backup deleted', [
                'backup_id' => $backup->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Backup deletion failed', [
                'user_id' => Auth::id(),
                'backup_id' => $backupId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete backup',
            ], 500);
        }
    }

    public function cancel(Request $request, string $backupId): JsonResponse
    {
        try {
            $user = Auth::user();
            $backup = ChatBackup::where('user_id', $user->id)->findOrFail($backupId);

            if (! $backup->isPending() && ! $backup->isProcessing()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Backup cannot be cancelled',
                ], 400);
            }

            $backup->markAsFailed('Cancelled by user');

            Log::info('Backup cancelled', [
                'backup_id' => $backup->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'backup' => [
                    'id' => $backup->id,
                    'status' => $backup->status,
                    'status_message' => $backup->status_message,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Backup cancellation failed', [
                'user_id' => Auth::id(),
                'backup_id' => $backupId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel backup',
            ], 500);
        }
    }

    public function progress(Request $request, string $backupId): JsonResponse
    {
        try {
            $user = Auth::user();
            $backup = ChatBackup::where('user_id', $user->id)->findOrFail($backupId);

            return response()->json([
                'success' => true,
                'progress' => [
                    'status' => $backup->status,
                    'status_message' => $backup->status_message,
                    'progress_percentage' => $backup->progress_percentage,
                    'processed_items' => $backup->processed_items,
                    'total_items' => $backup->total_items,
                    'remaining_items' => $backup->getRemainingItems(),
                    'started_at' => $backup->started_at,
                    'duration_so_far' => $backup->started_at ? now()->diffInSeconds($backup->started_at) : 0,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get backup progress', [
                'user_id' => Auth::id(),
                'backup_id' => $backupId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get backup progress',
            ], 500);
        }
    }

    public function verify(Request $request, string $backupId): JsonResponse
    {
        try {
            $user = Auth::user();
            $backup = ChatBackup::where('user_id', $user->id)
                ->with('verification')
                ->findOrFail($backupId);

            if (! $backup->isCompleted() || ! $backup->hasBackupFile()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Backup cannot be verified',
                ], 400);
            }

            // Calculate current hash
            $currentHash = hash_file('sha256', $backup->backup_file_path);
            $isValid = hash_equals($backup->backup_file_hash, $currentHash);

            // Update verification record
            if ($backup->verification) {
                if ($isValid) {
                    $backup->verification->markAsVerified('Hash verification passed');
                } else {
                    $backup->verification->markAsFailed('Hash verification failed');
                }
            }

            Log::info('Backup verification completed', [
                'backup_id' => $backup->id,
                'user_id' => $user->id,
                'is_valid' => $isValid,
            ]);

            return response()->json([
                'success' => true,
                'verification' => [
                    'is_verified' => $isValid,
                    'expected_hash' => $backup->backup_file_hash,
                    'actual_hash' => $currentHash,
                    'verified_at' => now(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Backup verification failed', [
                'user_id' => Auth::id(),
                'backup_id' => $backupId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to verify backup',
            ], 500);
        }
    }
}
