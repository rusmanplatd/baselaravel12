<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KeyRecoveryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KeyRecoveryController extends Controller
{
    protected KeyRecoveryService $keyRecoveryService;

    public function __construct(KeyRecoveryService $keyRecoveryService)
    {
        $this->keyRecoveryService = $keyRecoveryService;
    }

    /**
     * Create a full backup of user's encryption keys
     */
    public function createBackup(Request $request): JsonResponse
    {
        $request->validate([
            'master_password' => 'sometimes|string|min:8',
            'store_backup' => 'sometimes|boolean',
        ]);

        try {
            $userId = Auth::id();
            $masterPassword = $request->input('master_password');

            $backup = $this->keyRecoveryService->createUserBackup($userId, $masterPassword);

            $filename = null;
            if ($request->boolean('store_backup', false)) {
                $filename = $this->keyRecoveryService->storeBackup($backup);
            }

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'backup_id' => $backup['backup_id'] ?? 'unknown',
                'backup_timestamp' => $backup['backup_timestamp'] ?? now()->toISOString(),
                'conversations_count' => count($backup['conversations'] ?? []),
                'encrypted' => isset($backup['encrypted']) && $backup['encrypted'],
                'stored_filename' => $filename,
                'backup_data' => $request->boolean('include_data', false) ? $backup : null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create an incremental backup
     */
    public function createIncrementalBackup(Request $request): JsonResponse
    {
        $request->validate([
            'since_timestamp' => 'required|date',
            'master_password' => 'sometimes|string|min:8',
            'store_backup' => 'sometimes|boolean',
        ]);

        try {
            $userId = Auth::id();
            $sinceTimestamp = Carbon::parse($request->input('since_timestamp'));
            $masterPassword = $request->input('master_password');

            $backup = $this->keyRecoveryService->createIncrementalBackup(
                $userId,
                $sinceTimestamp,
                $masterPassword
            );

            $filename = null;
            if ($request->boolean('store_backup', false)) {
                $filename = $this->keyRecoveryService->storeBackup($backup);
            }

            return response()->json([
                'success' => true,
                'message' => 'Incremental backup created successfully',
                'backup_id' => $backup['backup_id'] ?? 'unknown',
                'since_timestamp' => $sinceTimestamp->toISOString(),
                'backup_timestamp' => $backup['backup_timestamp'] ?? now()->toISOString(),
                'changes_count' => count($backup['changes'] ?? []),
                'encrypted' => isset($backup['encrypted']) && $backup['encrypted'],
                'stored_filename' => $filename,
                'backup_data' => $request->boolean('include_data', false) ? $backup : null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create incremental backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore keys from backup
     */
    public function restoreFromBackup(Request $request): JsonResponse
    {
        $request->validate([
            'backup_data' => 'required|array',
            'private_key' => 'required|string',
            'master_password' => 'sometimes|string|min:8',
        ]);

        try {
            $backupData = $request->input('backup_data');
            $privateKey = $request->input('private_key');
            $masterPassword = $request->input('master_password');

            $result = $this->keyRecoveryService->restoreFromBackup(
                $backupData,
                $privateKey,
                $masterPassword
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success']
                    ? 'Backup restored successfully'
                    : 'Backup restoration completed with errors',
                'restored_count' => $result['restored_count'],
                'total_conversations' => $result['total_conversations'],
                'errors_count' => count($result['errors']),
                'errors' => $result['errors'],
                'backup_id' => $result['backup_id'],
                'backup_timestamp' => $result['backup_timestamp'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore from backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Load backup from storage
     */
    public function loadBackup(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => 'required|string',
        ]);

        try {
            $filename = $request->input('filename');
            $backup = $this->keyRecoveryService->loadBackup($filename);

            return response()->json([
                'success' => true,
                'message' => 'Backup loaded successfully',
                'backup_id' => $backup['backup_id'] ?? 'unknown',
                'backup_timestamp' => $backup['backup_timestamp'] ?? null,
                'encrypted' => isset($backup['encrypted']) && $backup['encrypted'],
                'conversations_count' => count($backup['conversations'] ?? []),
                'backup_data' => $backup,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recovery status for current user
     */
    public function getRecoveryStatus(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $status = $this->keyRecoveryService->getRecoveryStatus($userId);

            return response()->json([
                'success' => true,
                'status' => $status,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recovery status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate backup integrity
     */
    public function validateBackup(Request $request): JsonResponse
    {
        $request->validate([
            'backup_data' => 'required|array',
        ]);

        try {
            $backupData = $request->input('backup_data');
            $isValid = $this->keyRecoveryService->validateBackupIntegrity($backupData);

            return response()->json([
                'success' => true,
                'valid' => $isValid,
                'message' => $isValid ? 'Backup integrity is valid' : 'Backup integrity check failed',
                'backup_id' => $backupData['backup_id'] ?? 'unknown',
                'backup_version' => $backupData['version'] ?? 'unknown',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate backup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Emergency recovery (admin only)
     */
    public function performEmergencyRecovery(Request $request): JsonResponse
    {
        // Check if user has admin permissions
        if (! Auth::user()->hasRole('admin') && ! Auth::user()->can('perform emergency recovery')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin privileges required.',
            ], 403);
        }

        $request->validate([
            'user_id' => 'required|string|exists:sys_users,id',
            'recovery_data' => 'required|array',
            'recovery_data.new_key_pair_required' => 'required|boolean',
            'recovery_data.conversations_to_recover' => 'required|array',
            'recovery_data.conversations_to_recover.*.conversation_id' => 'required|string',
            'recovery_data.conversations_to_recover.*.emergency_symmetric_key' => 'required|string',
        ]);

        try {
            $userId = $request->input('user_id');
            $recoveryData = $request->input('recovery_data');
            $adminUserId = Auth::id();

            $result = $this->keyRecoveryService->performEmergencyRecovery(
                $userId,
                $recoveryData,
                $adminUserId
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success']
                    ? 'Emergency recovery completed successfully'
                    : 'Emergency recovery completed with errors',
                'recovery_id' => $result['recovery_id'],
                'user_id' => $result['user_id'],
                'admin_user_id' => $result['admin_user_id'],
                'recovery_timestamp' => $result['recovery_timestamp'],
                'new_key_pair_generated' => $result['new_key_pair_generated'],
                'new_private_key' => $result['new_private_key'], // Only returned once!
                'restored_count' => $result['restored_count'],
                'total_conversations' => $result['total_conversations'],
                'errors' => $result['errors'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Emergency recovery failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
