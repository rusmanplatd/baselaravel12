<?php

namespace App\Services;

use App\Exceptions\EncryptionException;
use App\Models\Chat\EncryptionKey;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KeyRecoveryService
{
    protected ChatEncryptionService $encryptionService;

    public function __construct(ChatEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Create a comprehensive backup of user's encryption keys
     */
    public function createUserBackup(string $userId, ?string $masterPassword = null): array
    {
        try {
            $user = User::findOrFail($userId);
            $backupId = 'backup-'.date('Y-m-d-H-i-s').'-'.bin2hex(random_bytes(8));
            
            // Get all encryption keys for the user
            $encryptionKeys = $user->encryptionKeys()
                ->with(['conversation', 'device'])
                ->active()
                ->get();

            if ($encryptionKeys->isEmpty()) {
                throw new EncryptionException('No encryption keys found for user');
            }

            // Build comprehensive backup data
            $backupData = [
                'backup_id' => $backupId,
                'version' => '2.0',
                'user_id' => $userId,
                'backup_timestamp' => now()->toISOString(),
                'public_key' => $user->public_key,
                'conversations' => $encryptionKeys->map(function ($key) {
                    return [
                        'conversation_id' => $key->conversation_id,
                        'conversation_name' => $key->conversation->name ?? 'Direct Chat',
                        'conversation_type' => $key->conversation->type ?? 'direct',
                        'encrypted_key' => $key->encrypted_key,
                        'key_version' => $key->key_version,
                        'algorithm' => $key->algorithm,
                        'key_strength' => $key->key_strength,
                        'device_id' => $key->device_id,
                        'device_name' => $key->device->device_name ?? 'Unknown Device',
                        'device_fingerprint' => $key->device_fingerprint,
                        'created_at' => $key->created_at->toISOString(),
                        'is_active' => $key->is_active,
                    ];
                })->toArray(),
                'devices' => $user->devices->map(function ($device) {
                    return [
                        'device_id' => $device->id,
                        'device_name' => $device->device_name,
                        'device_type' => $device->device_type,
                        'platform' => $device->platform,
                        'device_fingerprint' => $device->device_fingerprint,
                        'public_key' => $device->public_key,
                        'is_trusted' => $device->is_trusted,
                        'last_used_at' => $device->last_used_at?->toISOString(),
                    ];
                })->toArray(),
                'metadata' => [
                    'total_conversations' => $encryptionKeys->groupBy('conversation_id')->count(),
                    'total_devices' => $user->devices->count(),
                    'backup_version' => '2.0',
                    'encryption_algorithm' => 'RSA-4096-OAEP',
                    'created_by' => 'KeyRecoveryService',
                ],
            ];

            // Calculate checksum for integrity verification
            $checksum = hash('sha256', json_encode([
                'conversations' => $backupData['conversations'],
                'devices' => $backupData['devices']
            ]));
            $backupData['checksum'] = $checksum;

            // Encrypt backup if master password is provided
            if ($masterPassword) {
                $encryptedBackup = $this->encryptBackup($backupData, $masterPassword);
                $backupData = [
                    'encrypted' => true,
                    'backup_id' => $backupId,
                    'data' => $encryptedBackup
                ];
            }

            Log::info('User backup created successfully', [
                'user_id' => $userId,
                'backup_id' => $backupId,
                'conversations_count' => count($backupData['conversations'] ?? []),
                'encrypted' => isset($backupData['encrypted']),
            ]);

            return $backupData;

        } catch (\Exception $e) {
            Log::error('Failed to create user backup', [
                'user_id' => $userId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EncryptionException('Backup creation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Create incremental backup with changes since a specific timestamp
     */
    public function createIncrementalBackup(string $userId, Carbon $sinceTimestamp, ?string $masterPassword = null): array
    {
        try {
            $user = User::findOrFail($userId);
            $backupId = 'incremental-'.date('Y-m-d-H-i-s').'-'.bin2hex(random_bytes(8));

            // Get encryption keys created or modified after the timestamp
            $newKeys = $user->encryptionKeys()
                ->with(['conversation', 'device'])
                ->where('created_at', '>', $sinceTimestamp)
                ->orWhere('updated_at', '>', $sinceTimestamp)
                ->active()
                ->get();

            $backupData = [
                'backup_id' => $backupId,
                'backup_type' => 'incremental',
                'version' => '2.0',
                'user_id' => $userId,
                'since_timestamp' => $sinceTimestamp->toISOString(),
                'backup_timestamp' => now()->toISOString(),
                'changes' => $newKeys->map(function ($key) {
                    return [
                        'conversation_id' => $key->conversation_id,
                        'encrypted_key' => $key->encrypted_key,
                        'change_type' => $key->created_at > $key->updated_at ? 'added' : 'modified',
                        'added_at' => $key->created_at->toISOString(),
                        'device_id' => $key->device_id,
                    ];
                })->toArray(),
                'metadata' => [
                    'changes_count' => $newKeys->count(),
                    'backup_version' => '2.0',
                    'base_backup_required' => true,
                ],
            ];

            if ($masterPassword) {
                $encryptedBackup = $this->encryptBackup($backupData, $masterPassword);
                $backupData = [
                    'encrypted' => true,
                    'backup_id' => $backupId,
                    'data' => $encryptedBackup
                ];
            }

            Log::info('Incremental backup created successfully', [
                'user_id' => $userId,
                'backup_id' => $backupId,
                'changes_count' => count($backupData['changes'] ?? []),
                'since_timestamp' => $sinceTimestamp->toISOString(),
            ]);

            return $backupData;

        } catch (\Exception $e) {
            Log::error('Failed to create incremental backup', [
                'user_id' => $userId,
                'exception' => $e->getMessage()
            ]);
            throw new EncryptionException('Incremental backup creation failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Restore user's encryption keys from backup
     */
    public function restoreFromBackup(array $backupData, string $privateKey, ?string $masterPassword = null): array
    {
        try {
            // Decrypt backup if it's encrypted
            if (isset($backupData['encrypted']) && $backupData['encrypted']) {
                if (!$masterPassword) {
                    throw new EncryptionException('Master password required for encrypted backup');
                }
                $backupData = $this->decryptBackup($backupData['data'], $masterPassword);
            }

            // Validate backup integrity
            if (!$this->validateBackupIntegrity($backupData)) {
                throw new EncryptionException('Backup integrity validation failed');
            }

            $userId = $backupData['user_id'];
            $user = User::findOrFail($userId);
            $restoredCount = 0;
            $errors = [];

            DB::transaction(function () use ($backupData, $user, $privateKey, &$restoredCount, &$errors) {
                foreach ($backupData['conversations'] as $convData) {
                    try {
                        // Verify we can decrypt the symmetric key with the provided private key
                        $symmetricKey = $this->encryptionService->decryptSymmetricKey(
                            $convData['encrypted_key'],
                            $privateKey
                        );

                        // Get or create device for restoration
                        $device = $this->getOrCreateRecoveryDevice($user->id, $user->public_key);

                        // Check if key already exists to avoid duplicates
                        $existingKey = EncryptionKey::where('conversation_id', $convData['conversation_id'])
                            ->where('user_id', $user->id)
                            ->where('device_id', $device->id)
                            ->first();

                        if (!$existingKey) {
                            EncryptionKey::create([
                                'conversation_id' => $convData['conversation_id'],
                                'user_id' => $user->id,
                                'device_id' => $device->id,
                                'device_fingerprint' => $device->device_fingerprint,
                                'encrypted_key' => $convData['encrypted_key'],
                                'public_key' => $user->public_key,
                                'key_version' => $convData['key_version'] ?? 1,
                                'algorithm' => $convData['algorithm'] ?? 'RSA-4096-OAEP',
                                'key_strength' => $convData['key_strength'] ?? 4096,
                                'is_active' => true,
                            ]);
                            $restoredCount++;
                        }

                    } catch (\Exception $e) {
                        $errors[] = [
                            'conversation_id' => $convData['conversation_id'],
                            'error' => $e->getMessage()
                        ];
                        Log::warning('Failed to restore encryption key', [
                            'conversation_id' => $convData['conversation_id'],
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });

            $result = [
                'restored_count' => $restoredCount,
                'total_conversations' => count($backupData['conversations']),
                'errors' => $errors,
                'success' => $restoredCount > 0,
                'backup_id' => $backupData['backup_id'] ?? 'unknown',
                'backup_timestamp' => $backupData['backup_timestamp'] ?? null,
            ];

            Log::info('Backup restoration completed', [
                'user_id' => $userId,
                'restored_count' => $restoredCount,
                'total_conversations' => count($backupData['conversations']),
                'errors_count' => count($errors)
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to restore from backup', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EncryptionException('Backup restoration failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Perform emergency recovery with admin assistance
     */
    public function performEmergencyRecovery(string $userId, array $recoveryData, string $adminUserId): array
    {
        try {
            $user = User::findOrFail($userId);
            $adminUser = User::findOrFail($adminUserId);
            $recoveryId = 'emergency-'.date('Y-m-d-H-i-s').'-'.bin2hex(random_bytes(8));

            // Generate new key pair for user if needed
            if ($recoveryData['new_key_pair_required'] ?? false) {
                $newKeyPair = $this->encryptionService->generateKeyPair();
                $user->update(['public_key' => $newKeyPair['public_key']]);
            } else {
                $newKeyPair = ['public_key' => $user->public_key];
            }

            $restoredCount = 0;
            $errors = [];

            DB::transaction(function () use ($recoveryData, $user, $newKeyPair, &$restoredCount, &$errors) {
                foreach ($recoveryData['conversations_to_recover'] as $convData) {
                    try {
                        // Get or create emergency recovery device
                        $device = $this->getOrCreateEmergencyDevice($user->id, $newKeyPair['public_key']);

                        // Encrypt the emergency symmetric key with the new public key
                        $encryptedKey = $this->encryptionService->encryptSymmetricKey(
                            $convData['emergency_symmetric_key'],
                            $newKeyPair['public_key']
                        );

                        EncryptionKey::create([
                            'conversation_id' => $convData['conversation_id'],
                            'user_id' => $user->id,
                            'device_id' => $device->id,
                            'device_fingerprint' => $device->device_fingerprint,
                            'encrypted_key' => $encryptedKey,
                            'public_key' => $newKeyPair['public_key'],
                            'algorithm' => 'RSA-4096-OAEP',
                            'key_strength' => 4096,
                            'is_active' => true,
                        ]);
                        $restoredCount++;

                    } catch (\Exception $e) {
                        $errors[] = [
                            'conversation_id' => $convData['conversation_id'],
                            'error' => $e->getMessage()
                        ];
                    }
                }
            });

            $result = [
                'recovery_id' => $recoveryId,
                'user_id' => $userId,
                'admin_user_id' => $adminUserId,
                'recovery_timestamp' => now()->toISOString(),
                'new_key_pair_generated' => $recoveryData['new_key_pair_required'] ?? false,
                'new_private_key' => $newKeyPair['private_key'] ?? null,
                'restored_count' => $restoredCount,
                'total_conversations' => count($recoveryData['conversations_to_recover']),
                'errors' => $errors,
                'success' => $restoredCount > 0,
            ];

            Log::info('Emergency recovery completed', [
                'recovery_id' => $recoveryId,
                'user_id' => $userId,
                'admin_user_id' => $adminUserId,
                'restored_count' => $restoredCount
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Emergency recovery failed', [
                'user_id' => $userId,
                'admin_user_id' => $adminUserId,
                'exception' => $e->getMessage()
            ]);
            throw new EncryptionException('Emergency recovery failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Validate backup integrity using checksum
     */
    public function validateBackupIntegrity(array $backupData): bool
    {
        if (!isset($backupData['checksum'])) {
            return false; // Older backup format without checksum
        }

        $dataForChecksum = [
            'conversations' => $backupData['conversations'] ?? [],
            'devices' => $backupData['devices'] ?? []
        ];

        $calculatedChecksum = hash('sha256', json_encode($dataForChecksum));
        return hash_equals($backupData['checksum'], $calculatedChecksum);
    }

    /**
     * Store backup securely in storage
     */
    public function storeBackup(array $backupData, string $filename = null): string
    {
        if (!$filename) {
            $filename = 'backup-' . ($backupData['backup_id'] ?? date('Y-m-d-H-i-s')) . '.json';
        }

        $disk = Storage::disk('backups');
        $disk->put($filename, json_encode($backupData, JSON_PRETTY_PRINT));

        Log::info('Backup stored successfully', [
            'filename' => $filename,
            'backup_id' => $backupData['backup_id'] ?? 'unknown'
        ]);

        return $filename;
    }

    /**
     * Load backup from storage
     */
    public function loadBackup(string $filename): array
    {
        $disk = Storage::disk('backups');
        
        if (!$disk->exists($filename)) {
            throw new EncryptionException("Backup file not found: {$filename}");
        }

        $backupData = json_decode($disk->get($filename), true);
        
        if (!$backupData) {
            throw new EncryptionException("Invalid backup file format: {$filename}");
        }

        return $backupData;
    }

    /**
     * Get recovery status and statistics for a user
     */
    public function getRecoveryStatus(string $userId): array
    {
        $user = User::findOrFail($userId);
        $encryptionKeys = $user->encryptionKeys()->with(['conversation', 'device'])->get();

        return [
            'user_id' => $userId,
            'user_email' => $user->email,
            'has_public_key' => !empty($user->public_key),
            'total_conversations' => $encryptionKeys->groupBy('conversation_id')->count(),
            'total_encryption_keys' => $encryptionKeys->count(),
            'active_keys' => $encryptionKeys->where('is_active', true)->count(),
            'devices_count' => $user->devices()->count(),
            'trusted_devices_count' => $user->devices()->where('is_trusted', true)->count(),
            'last_key_created' => $encryptionKeys->max('created_at'),
            'backup_recommendations' => $this->getBackupRecommendations($encryptionKeys),
        ];
    }

    /**
     * Get backup recommendations based on current state
     */
    protected function getBackupRecommendations(Collection $encryptionKeys): array
    {
        $recommendations = [];
        $totalKeys = $encryptionKeys->count();
        $lastBackupAge = null; // This would come from backup metadata in real implementation

        if ($totalKeys === 0) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'No encryption keys found. Set up end-to-end encryption first.'
            ];
        } elseif ($totalKeys > 0) {
            $recommendations[] = [
                'type' => 'info',
                'message' => "You have {$totalKeys} encryption keys. Consider creating a backup."
            ];
        }

        if ($totalKeys >= 5) {
            $recommendations[] = [
                'type' => 'suggestion',
                'message' => 'With multiple conversations, consider automated daily backups.'
            ];
        }

        return $recommendations;
    }

    /**
     * Encrypt backup with master password
     */
    protected function encryptBackup(array $backupData, string $masterPassword): string
    {
        return $this->encryptionService->createBackupEncryptionKey($masterPassword, $backupData);
    }

    /**
     * Decrypt backup with master password
     */
    protected function decryptBackup(string $encryptedBackup, string $masterPassword): array
    {
        return $this->encryptionService->restoreFromBackup($encryptedBackup, $masterPassword);
    }

    /**
     * Get or create a recovery device for the user
     */
    protected function getOrCreateRecoveryDevice(string $userId, string $publicKey): UserDevice
    {
        return UserDevice::firstOrCreate([
            'user_id' => $userId,
            'device_name' => 'Key Recovery Device',
        ], [
            'device_type' => 'web',
            'platform' => 'recovery',
            'public_key' => $publicKey,
            'device_fingerprint' => 'recovery-' . $userId . '-' . time(),
            'last_used_at' => now(),
            'is_trusted' => true,
        ]);
    }

    /**
     * Get or create an emergency recovery device
     */
    protected function getOrCreateEmergencyDevice(string $userId, string $publicKey): UserDevice
    {
        return UserDevice::firstOrCreate([
            'user_id' => $userId,
            'device_name' => 'Emergency Recovery Device',
        ], [
            'device_type' => 'web',
            'platform' => 'emergency',
            'public_key' => $publicKey,
            'device_fingerprint' => 'emergency-' . $userId . '-' . time(),
            'last_used_at' => now(),
            'is_trusted' => true,
        ]);
    }
}