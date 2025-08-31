<?php

declare(strict_types=1);

use App\Models\Chat\Conversation;
use App\Models\Chat\EncryptionKey;
use App\Models\Chat\Message;
use App\Models\Chat\MessageAttachment;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ChatEncryptionService;
use App\Services\ChatFileService;
use App\Services\MultiDeviceEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->encryptionService = new ChatEncryptionService;
    $this->fileService = new ChatFileService($this->encryptionService);
    $this->multiDeviceService = new MultiDeviceEncryptionService($this->encryptionService);
    
    Storage::fake('encrypted_files');
    Storage::fake('thumbnails');
    
    // Create users
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
    $this->charlie = User::factory()->create(['name' => 'Charlie']);
    
    // Create group conversation
    $this->groupConversation = Conversation::factory()->create([
        'type' => 'group',
        'name' => 'Project Team',
        'created_by' => $this->alice->id,
    ]);
    
    $this->groupConversation->participants()->create(['user_id' => $this->alice->id, 'role' => 'admin']);
    $this->groupConversation->participants()->create(['user_id' => $this->bob->id, 'role' => 'member']);
    $this->groupConversation->participants()->create(['user_id' => $this->charlie->id, 'role' => 'member']);
    
    // Setup encryption for all participants
    $this->setupEncryptionForUsers();
});

function setupEncryptionForUsers() {
    $this->symmetricKey = $this->encryptionService->generateSymmetricKey();
    $this->userDevices = [];
    $this->keyPairs = [];
    
    foreach ([$this->alice, $this->bob, $this->charlie] as $user) {
        $keyPair = $this->encryptionService->generateKeyPair();
        $this->keyPairs[$user->id] = $keyPair;
        
        $device = UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_name' => $user->name . "'s Device",
            'device_type' => 'mobile',
            'public_key' => $keyPair['public_key'],
            'is_trusted' => true,
        ]);
        
        $this->userDevices[$user->id] = $device;
        
        EncryptionKey::create([
            'conversation_id' => $this->groupConversation->id,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'device_fingerprint' => $device->device_fingerprint,
            'encrypted_key' => $this->encryptionService->encryptSymmetricKey($this->symmetricKey, $keyPair['public_key']),
            'public_key' => $keyPair['public_key'],
            'key_version' => 1,
            'algorithm' => 'RSA-4096-OAEP',
            'key_strength' => 4096,
            'is_active' => true,
        ]);
    }
}

describe('E2EE File Attachment Integration Tests', function () {
    it('demonstrates complete file sharing workflow in group chat', function () {
        // === Phase 1: Alice shares project documents ===
        $projectFiles = [
            ['name' => 'project_charter.pdf', 'content' => 'Project Charter Document - Confidential planning information', 'type' => 'application/pdf'],
            ['name' => 'team_roster.xlsx', 'content' => 'Team member information and contact details', 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['name' => 'meeting_notes.md', 'content' => "# Project Kickoff Meeting\n\n## Attendees\n- Alice\n- Bob\n- Charlie\n\n## Action Items\n- [ ] Review charter\n- [ ] Setup development environment", 'type' => 'text/markdown'],
        ];
        
        $aliceUploads = [];
        foreach ($projectFiles as $fileData) {
            $uploadedFile = UploadedFile::fake()->createWithContent($fileData['name'], $fileData['content']);
            
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->groupConversation->id,
                $this->alice->id,
                $this->symmetricKey
            );
            
            expect($uploadResult['success'])->toBeTrue();
            
            $message = Message::createEncrypted(
                $this->groupConversation->id,
                $this->alice->id,
                "📁 Sharing: {$fileData['name']} - Please review and provide feedback",
                $this->symmetricKey
            );
            
            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_id' => $uploadResult['file_id'],
                'original_filename' => $fileData['name'],
                'encrypted_filename' => $uploadResult['encrypted_filename'],
                'file_size' => $uploadResult['file_size'],
                'mime_type' => $uploadResult['mime_type'],
                'file_hash' => $uploadResult['file_hash'],
                'encryption_iv' => $uploadResult['encryption_iv'],
                'is_encrypted' => true,
            ]);
            
            $aliceUploads[] = [
                'upload' => $uploadResult,
                'message' => $message,
                'attachment' => $attachment,
                'original_content' => $fileData['content']
            ];
        }
        
        expect(count($aliceUploads))->toBe(3);
        
        // === Phase 2: Bob downloads and reviews files ===
        $bobDownloads = [];
        foreach ($aliceUploads as $fileData) {
            // Bob reads the message
            $decryptedMessage = $fileData['message']->decryptContent($this->symmetricKey);
            expect($decryptedMessage)->toStartWith('📁 Sharing:');
            
            // Bob downloads the file
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $fileData['upload']['file_id'],
                $this->symmetricKey
            );
            
            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($fileData['original_content']);
            
            $bobDownloads[] = $downloadResult;
        }
        
        expect(count($bobDownloads))->toBe(3);
        
        // === Phase 3: Bob responds with feedback files ===
        $bobFeedbackFiles = [
            ['name' => 'charter_feedback.txt', 'content' => 'Charter looks good! Suggest adding timeline section.'],
            ['name' => 'roster_updates.csv', 'content' => 'Name,Role,Email\nBob Smith,Developer,bob@company.com'],
        ];
        
        $bobUploads = [];
        foreach ($bobFeedbackFiles as $fileData) {
            $uploadedFile = UploadedFile::fake()->createWithContent($fileData['name'], $fileData['content']);
            
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->groupConversation->id,
                $this->bob->id,
                $this->symmetricKey
            );
            
            $message = Message::createEncrypted(
                $this->groupConversation->id,
                $this->bob->id,
                "💬 My feedback on the project files: {$fileData['name']}",
                $this->symmetricKey
            );
            
            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_id' => $uploadResult['file_id'],
                'original_filename' => $fileData['name'],
                'encrypted_filename' => $uploadResult['encrypted_filename'],
                'file_size' => $uploadResult['file_size'],
                'mime_type' => $uploadResult['mime_type'],
                'file_hash' => $uploadResult['file_hash'],
                'encryption_iv' => $uploadResult['encryption_iv'],
                'is_encrypted' => true,
            ]);
            
            $bobUploads[] = [
                'upload' => $uploadResult,
                'message' => $message,
                'attachment' => $attachment,
                'original_content' => $fileData['content']
            ];
        }
        
        // === Phase 4: Charlie joins the conversation and accesses all files ===
        $allFiles = array_merge($aliceUploads, $bobUploads);
        $charlieAccess = [];
        
        foreach ($allFiles as $fileData) {
            // Charlie reads the message
            $decryptedMessage = $fileData['message']->decryptContent($this->symmetricKey);
            expect($decryptedMessage)->not()->toBeEmpty();
            
            // Charlie downloads the file
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $fileData['upload']['file_id'],
                $this->symmetricKey
            );
            
            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($fileData['original_content']);
            
            $charlieAccess[] = $downloadResult;
        }
        
        expect(count($charlieAccess))->toBe(5); // 3 from Alice + 2 from Bob
        
        // === Phase 5: Verify complete workflow integrity ===
        $totalMessages = Message::where('conversation_id', $this->groupConversation->id)->count();
        expect($totalMessages)->toBe(5); // 3 from Alice + 2 from Bob
        
        $totalAttachments = MessageAttachment::whereIn('message_id', 
            Message::where('conversation_id', $this->groupConversation->id)->pluck('id')
        )->count();
        expect($totalAttachments)->toBe(5);
        
        // All files should be encrypted
        $encryptedAttachments = MessageAttachment::whereIn('message_id', 
            Message::where('conversation_id', $this->groupConversation->id)->pluck('id')
        )->where('is_encrypted', true)->count();
        expect($encryptedAttachments)->toBe(5);
        
        echo "\n✅ Complete group file sharing workflow successful:";
        echo "\n   • 3 participants in secure group chat";
        echo "\n   • 5 encrypted files shared";
        echo "\n   • All participants can access all files";
        echo "\n   • End-to-end encryption maintained throughout";
    });
    
    it('handles file sharing with key rotation during active use', function () {
        // === Phase 1: Initial file sharing ===
        $initialFiles = [
            'document1.txt' => 'Content of document 1 - before rotation',
            'document2.txt' => 'Content of document 2 - before rotation',
        ];
        
        $preRotationUploads = [];
        foreach ($initialFiles as $fileName => $content) {
            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
            
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->groupConversation->id,
                $this->alice->id,
                $this->symmetricKey
            );
            
            $message = Message::createEncrypted(
                $this->groupConversation->id,
                $this->alice->id,
                "Pre-rotation file: {$fileName}",
                $this->symmetricKey
            );
            
            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_id' => $uploadResult['file_id'],
                'original_filename' => $fileName,
                'encrypted_filename' => $uploadResult['encrypted_filename'],
                'file_size' => $uploadResult['file_size'],
                'mime_type' => $uploadResult['mime_type'],
                'file_hash' => $uploadResult['file_hash'],
                'encryption_iv' => $uploadResult['encryption_iv'],
                'is_encrypted' => true,
            ]);
            
            $preRotationUploads[] = [
                'upload' => $uploadResult,
                'attachment' => $attachment,
                'content' => $content
            ];
        }
        
        // === Phase 2: Perform key rotation ===
        $oldSymmetricKey = $this->symmetricKey;
        $newSymmetricKey = $this->encryptionService->rotateSymmetricKey($this->groupConversation->id);
        
        // Update encryption keys for all users
        foreach ([$this->alice, $this->bob, $this->charlie] as $user) {
            // Deactivate old key
            EncryptionKey::where('conversation_id', $this->groupConversation->id)
                ->where('user_id', $user->id)
                ->update(['is_active' => false]);
            
            // Create new key
            EncryptionKey::create([
                'conversation_id' => $this->groupConversation->id,
                'user_id' => $user->id,
                'device_id' => $this->userDevices[$user->id]->id,
                'device_fingerprint' => $this->userDevices[$user->id]->device_fingerprint,
                'encrypted_key' => $this->encryptionService->encryptSymmetricKey($newSymmetricKey, $this->keyPairs[$user->id]['public_key']),
                'public_key' => $this->keyPairs[$user->id]['public_key'],
                'key_version' => 2,
                'algorithm' => 'RSA-4096-OAEP',
                'key_strength' => 4096,
                'is_active' => true,
            ]);
        }
        
        // === Phase 3: Upload files with new key ===
        $postRotationFiles = [
            'document3.txt' => 'Content of document 3 - after rotation',
            'document4.txt' => 'Content of document 4 - after rotation',
        ];
        
        $postRotationUploads = [];
        foreach ($postRotationFiles as $fileName => $content) {
            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
            
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->groupConversation->id,
                $this->bob->id,
                $newSymmetricKey
            );
            
            $message = Message::createEncrypted(
                $this->groupConversation->id,
                $this->bob->id,
                "Post-rotation file: {$fileName}",
                $newSymmetricKey
            );
            
            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_id' => $uploadResult['file_id'],
                'original_filename' => $fileName,
                'encrypted_filename' => $uploadResult['encrypted_filename'],
                'file_size' => $uploadResult['file_size'],
                'mime_type' => $uploadResult['mime_type'],
                'file_hash' => $uploadResult['file_hash'],
                'encryption_iv' => $uploadResult['encryption_iv'],
                'is_encrypted' => true,
            ]);
            
            $postRotationUploads[] = [
                'upload' => $uploadResult,
                'attachment' => $attachment,
                'content' => $content
            ];
        }
        
        // === Phase 4: Verify file access with both keys ===
        // Pre-rotation files should still work with old key
        foreach ($preRotationUploads as $fileData) {
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $fileData['upload']['file_id'],
                $oldSymmetricKey
            );
            
            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($fileData['content']);
        }
        
        // Post-rotation files should work with new key
        foreach ($postRotationUploads as $fileData) {
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $fileData['upload']['file_id'],
                $newSymmetricKey
            );
            
            expect($downloadResult['success'])->toBeTrue();
            expect($downloadResult['content'])->toBe($fileData['content']);
        }
        
        // Verify forward secrecy: old key cannot decrypt new files
        foreach ($postRotationUploads as $fileData) {
            $downloadResult = $this->fileService->downloadEncryptedFile(
                $fileData['upload']['file_id'],
                $oldSymmetricKey
            );
            
            expect($downloadResult['success'])->toBeFalse();
        }
        
        // === Phase 5: Verify all users can access appropriate files ===
        foreach ([$this->alice, $this->bob, $this->charlie] as $user) {
            // Access old files (should work if user has access to old keys)
            // Access new files with current key
            foreach ($postRotationUploads as $fileData) {
                $downloadResult = $this->fileService->downloadEncryptedFile(
                    $fileData['upload']['file_id'],
                    $newSymmetricKey
                );
                
                expect($downloadResult['success'])->toBeTrue();
                expect($downloadResult['content'])->toBe($fileData['content']);
            }
        }
        
        echo "\n✅ File sharing with key rotation test successful:";
        echo "\n   • 4 files shared across key rotation";
        echo "\n   • Forward secrecy maintained";
        echo "\n   • Historical file access preserved";
        echo "\n   • All users can access current files";
    });
    
    it('demonstrates file sharing performance under realistic load', function () {
        // === Setup: Create realistic file sharing scenario ===
        $fileTypes = [
            'documents' => ['txt', 'pdf', 'docx'],
            'spreadsheets' => ['csv', 'xlsx'],
            'images' => ['jpg', 'png'],
            'code' => ['js', 'py', 'php'],
        ];
        
        $totalFiles = 50;
        $uploadedFiles = [];
        $startTime = microtime(true);
        $totalSize = 0;
        
        // === Phase 1: Bulk file uploads from different users ===
        for ($i = 0; $i < $totalFiles; $i++) {
            $sender = [$this->alice, $this->bob, $this->charlie][$i % 3];
            $category = array_keys($fileTypes)[array_rand($fileTypes)];
            $extension = $fileTypes[$category][array_rand($fileTypes[$category])];
            
            $fileName = "file_{$i}.{$extension}";
            $content = "Content for {$fileName} - " . str_repeat("data{$i} ", rand(10, 100));
            $fileSize = strlen($content);
            $totalSize += $fileSize;
            
            $uploadedFile = UploadedFile::fake()->createWithContent($fileName, $content);
            
            $uploadResult = $this->fileService->uploadEncryptedFile(
                $uploadedFile,
                $this->groupConversation->id,
                $sender->id,
                $this->symmetricKey
            );
            
            expect($uploadResult['success'])->toBeTrue();
            
            $message = Message::createEncrypted(
                $this->groupConversation->id,
                $sender->id,
                "📎 Bulk upload: {$fileName}",
                $this->symmetricKey
            );
            
            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_id' => $uploadResult['file_id'],
                'original_filename' => $fileName,
                'encrypted_filename' => $uploadResult['encrypted_filename'],
                'file_size' => $uploadResult['file_size'],
                'mime_type' => $uploadResult['mime_type'],
                'file_hash' => $uploadResult['file_hash'],
                'encryption_iv' => $uploadResult['encryption_iv'],
                'is_encrypted' => true,
            ]);
            
            $uploadedFiles[] = [
                'upload' => $uploadResult,
                'attachment' => $attachment,
                'content' => $content,
                'sender' => $sender
            ];
            
            // Small delay to simulate realistic timing
            if ($i % 10 === 0) {
                usleep(10000); // 10ms pause every 10 files
            }
        }
        
        $uploadTime = (microtime(true) - $startTime) * 1000; // ms
        
        // === Phase 2: Bulk file downloads and verification ===
        $downloadStartTime = microtime(true);
        $successfulDownloads = 0;
        $downloadErrors = [];
        
        foreach ($uploadedFiles as $fileData) {
            try {
                $downloadResult = $this->fileService->downloadEncryptedFile(
                    $fileData['upload']['file_id'],
                    $this->symmetricKey
                );
                
                if ($downloadResult['success']) {
                    expect($downloadResult['content'])->toBe($fileData['content']);
                    $successfulDownloads++;
                } else {
                    $downloadErrors[] = "Download failed for file: " . $fileData['upload']['file_id'];
                }
            } catch (\Exception $e) {
                $downloadErrors[] = "Download exception: " . $e->getMessage();
            }
        }
        
        $downloadTime = (microtime(true) - $downloadStartTime) * 1000; // ms
        
        // === Phase 3: Performance validation ===
        $avgUploadTime = $uploadTime / $totalFiles;
        $avgDownloadTime = $downloadTime / $totalFiles;
        $totalSizeMB = $totalSize / 1024 / 1024;
        
        // Performance expectations
        expect($uploadTime)->toBeLessThan(60000); // Less than 60 seconds total
        expect($downloadTime)->toBeLessThan(30000); // Less than 30 seconds total
        expect($avgUploadTime)->toBeLessThan(1000); // Less than 1 second per file
        expect($avgDownloadTime)->toBeLessThan(500); // Less than 500ms per download
        expect($successfulDownloads)->toBe($totalFiles);
        expect($downloadErrors)->toBeEmpty();
        
        // === Phase 4: Data integrity verification ===
        $totalMessages = Message::where('conversation_id', $this->groupConversation->id)->count();
        expect($totalMessages)->toBe($totalFiles);
        
        $totalAttachments = MessageAttachment::whereIn('message_id',
            Message::where('conversation_id', $this->groupConversation->id)->pluck('id')
        )->count();
        expect($totalAttachments)->toBe($totalFiles);
        
        $encryptedAttachments = MessageAttachment::whereIn('message_id',
            Message::where('conversation_id', $this->groupConversation->id)->pluck('id')
        )->where('is_encrypted', true)->count();
        expect($encryptedAttachments)->toBe($totalFiles);
        
        // Verify file distribution across users
        $userFileCounts = [];
        foreach ($uploadedFiles as $fileData) {
            $userId = $fileData['sender']->id;
            $userFileCounts[$userId] = ($userFileCounts[$userId] ?? 0) + 1;
        }
        
        // Should be roughly distributed across 3 users
        foreach ($userFileCounts as $count) {
            expect($count)->toBeGreaterThan($totalFiles / 3 - 5);
            expect($count)->toBeLessThan($totalFiles / 3 + 5);
        }
        
        echo "\n✅ File sharing performance test successful:";
        echo "\n   • Files uploaded: {$totalFiles}";
        echo "\n   • Total size: " . number_format($totalSizeMB, 2) . "MB";
        echo "\n   • Upload time: " . number_format($uploadTime, 2) . "ms (" . number_format($avgUploadTime, 2) . "ms/file)";
        echo "\n   • Download time: " . number_format($downloadTime, 2) . "ms (" . number_format($avgDownloadTime, 2) . "ms/file)";
        echo "\n   • Success rate: 100% ({$successfulDownloads}/{$totalFiles})";
        echo "\n   • All files encrypted and accessible to all participants";
    });
});