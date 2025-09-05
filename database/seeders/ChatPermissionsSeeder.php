<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating chat-specific permissions and roles...');

        // Chat-specific permissions
        $chatPermissions = [
            // Message permissions
            'chat.messages.send' => 'Send messages in conversations',
            'chat.messages.edit' => 'Edit own messages',
            'chat.messages.delete' => 'Delete own messages',
            'chat.messages.moderate' => 'Moderate messages (admin)',
            'chat.messages.view_deleted' => 'View deleted messages',

            // Conversation permissions
            'chat.conversations.create' => 'Create new conversations',
            'chat.conversations.join' => 'Join conversations',
            'chat.conversations.leave' => 'Leave conversations',
            'chat.conversations.invite' => 'Invite users to conversations',
            'chat.conversations.moderate' => 'Moderate conversations',
            'chat.conversations.delete' => 'Delete conversations',

            // File sharing permissions
            'chat.files.upload' => 'Upload files to conversations',
            'chat.files.download' => 'Download shared files',
            'chat.files.moderate' => 'Moderate file uploads',
            'chat.files.delete' => 'Delete uploaded files',

            // Video/Audio call permissions
            'chat.calls.initiate' => 'Initiate video/audio calls',
            'chat.calls.join' => 'Join video/audio calls',
            'chat.calls.record' => 'Record video/audio calls',
            'chat.calls.moderate' => 'Moderate calls (end, kick participants)',

            // Encryption permissions
            'chat.encryption.manage' => 'Manage encryption settings',
            'chat.encryption.view_keys' => 'View encryption key information',
            'chat.encryption.rotate_keys' => 'Rotate encryption keys',

            // Polls and surveys
            'chat.polls.create' => 'Create polls and surveys',
            'chat.polls.vote' => 'Vote in polls',
            'chat.polls.moderate' => 'Moderate polls and surveys',
            'chat.polls.view_results' => 'View poll results',

            // Backup and export
            'chat.backup.create' => 'Create chat backups',
            'chat.backup.download' => 'Download chat backups',
            'chat.backup.restore' => 'Restore from backups',

            // Abuse reporting
            'chat.reports.create' => 'Submit abuse reports',
            'chat.reports.review' => 'Review abuse reports',
            'chat.reports.moderate' => 'Take action on reports',

            // Rate limiting and penalties
            'chat.penalties.view' => 'View user penalties',
            'chat.penalties.apply' => 'Apply penalties to users',
            'chat.penalties.remove' => 'Remove penalties from users',
            'chat.rate_limits.manage' => 'Manage rate limit configurations',

            // Analytics and monitoring
            'chat.analytics.view' => 'View chat analytics',
            'chat.quality.monitor' => 'Monitor call quality metrics',
            'chat.events.view' => 'View system events and logs',

            // Device management
            'chat.devices.manage' => 'Manage user devices',
            'chat.devices.revoke' => 'Revoke device access',

            // Advanced permissions
            'chat.admin.all' => 'Full chat administration access',
            'chat.system.config' => 'Configure chat system settings',
            'chat.webhooks.manage' => 'Manage webhook configurations',
        ];

        $existingPermissions = DB::table('sys_permissions')
            ->whereIn('name', array_keys($chatPermissions))
            ->pluck('name')
            ->toArray();

        $systemUserId = DB::table('sys_users')->first()->id ?? '01k4b90tnc9prnh76x4am6q069';
        
        foreach ($chatPermissions as $name => $description) {
            if (!in_array($name, $existingPermissions)) {
                DB::table('sys_permissions')->insert([
                    'id' => Str::ulid(),
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => $systemUserId,
                    'updated_by' => $systemUserId,
                ]);
            }
        }

        $this->command->info('Created ' . count($chatPermissions) . ' chat permissions.');

        // Define roles and their permissions
        $roles = [
            'chat_user' => [
                'description' => 'Standard chat user with basic messaging capabilities',
                'permissions' => [
                    'chat.messages.send',
                    'chat.messages.edit',
                    'chat.messages.delete',
                    'chat.conversations.create',
                    'chat.conversations.join',
                    'chat.conversations.leave',
                    'chat.conversations.invite',
                    'chat.files.upload',
                    'chat.files.download',
                    'chat.calls.initiate',
                    'chat.calls.join',
                    'chat.polls.create',
                    'chat.polls.vote',
                    'chat.polls.view_results',
                    'chat.backup.create',
                    'chat.backup.download',
                    'chat.reports.create',
                    'chat.devices.manage',
                ],
            ],
            'chat_moderator' => [
                'description' => 'Chat moderator with content moderation capabilities',
                'permissions' => [
                    // Include all chat_user permissions
                    'chat.messages.send',
                    'chat.messages.edit',
                    'chat.messages.delete',
                    'chat.messages.moderate',
                    'chat.messages.view_deleted',
                    'chat.conversations.create',
                    'chat.conversations.join',
                    'chat.conversations.leave',
                    'chat.conversations.invite',
                    'chat.conversations.moderate',
                    'chat.files.upload',
                    'chat.files.download',
                    'chat.files.moderate',
                    'chat.files.delete',
                    'chat.calls.initiate',
                    'chat.calls.join',
                    'chat.calls.moderate',
                    'chat.polls.create',
                    'chat.polls.vote',
                    'chat.polls.moderate',
                    'chat.polls.view_results',
                    'chat.backup.create',
                    'chat.backup.download',
                    'chat.reports.create',
                    'chat.reports.review',
                    'chat.reports.moderate',
                    'chat.penalties.view',
                    'chat.penalties.apply',
                    'chat.devices.manage',
                    'chat.devices.revoke',
                ],
            ],
            'chat_admin' => [
                'description' => 'Chat administrator with full system access',
                'permissions' => [
                    // Include all permissions
                    'chat.admin.all',
                    'chat.system.config',
                    'chat.rate_limits.manage',
                    'chat.penalties.view',
                    'chat.penalties.apply',
                    'chat.penalties.remove',
                    'chat.analytics.view',
                    'chat.quality.monitor',
                    'chat.events.view',
                    'chat.encryption.manage',
                    'chat.encryption.view_keys',
                    'chat.encryption.rotate_keys',
                    'chat.backup.restore',
                    'chat.webhooks.manage',
                    'chat.calls.record',
                ],
            ],
            'chat_guest' => [
                'description' => 'Limited guest user with restricted access',
                'permissions' => [
                    'chat.messages.send',
                    'chat.conversations.join',
                    'chat.files.download',
                    'chat.calls.join',
                    'chat.polls.vote',
                ],
            ],
            'chat_bot' => [
                'description' => 'Bot user for automated interactions',
                'permissions' => [
                    'chat.messages.send',
                    'chat.conversations.join',
                    'chat.files.upload',
                    'chat.polls.create',
                    'chat.analytics.view',
                ],
            ],
        ];

        $existingRoles = DB::table('sys_roles')
            ->whereIn('name', array_keys($roles))
            ->pluck('name')
            ->toArray();

        foreach ($roles as $roleName => $roleData) {
            $roleId = Str::ulid();
            
            if (!in_array($roleName, $existingRoles)) {
                DB::table('sys_roles')->insert([
                    'id' => $roleId,
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => $systemUserId,
                    'updated_by' => $systemUserId,
                ]);
            } else {
                $roleId = DB::table('sys_roles')->where('name', $roleName)->value('id');
            }

            // Get permission IDs
            $permissionIds = DB::table('sys_permissions')
                ->whereIn('name', $roleData['permissions'])
                ->pluck('id')
                ->toArray();

            // Insert role permissions
            foreach ($permissionIds as $permissionId) {
                DB::table('sys_role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }

            $this->command->info("Created role: {$roleName} with " . count($roleData['permissions']) . ' permissions.');
        }

        // Create conversation-specific roles (these would be assigned per conversation)
        $conversationRoles = [
            'conversation_owner' => [
                'description' => 'Owner of a specific conversation',
                'permissions' => [
                    'chat.conversations.moderate',
                    'chat.conversations.delete',
                    'chat.messages.moderate',
                    'chat.files.moderate',
                    'chat.calls.moderate',
                    'chat.encryption.manage',
                ],
            ],
            'conversation_admin' => [
                'description' => 'Administrator of a specific conversation',
                'permissions' => [
                    'chat.conversations.moderate',
                    'chat.messages.moderate',
                    'chat.files.moderate',
                    'chat.calls.moderate',
                ],
            ],
            'conversation_member' => [
                'description' => 'Regular member of a conversation',
                'permissions' => [
                    'chat.messages.send',
                    'chat.messages.edit',
                    'chat.messages.delete',
                    'chat.files.upload',
                    'chat.files.download',
                    'chat.calls.initiate',
                    'chat.calls.join',
                    'chat.polls.create',
                    'chat.polls.vote',
                ],
            ],
            'conversation_readonly' => [
                'description' => 'Read-only access to a conversation',
                'permissions' => [
                    'chat.files.download',
                    'chat.calls.join',
                    'chat.polls.vote',
                ],
            ],
        ];

        $existingConversationRoles = DB::table('sys_roles')
            ->whereIn('name', array_keys($conversationRoles))
            ->pluck('name')
            ->toArray();

        foreach ($conversationRoles as $roleName => $roleData) {
            $roleId = Str::ulid();
            
            if (!in_array($roleName, $existingConversationRoles)) {
                DB::table('sys_roles')->insert([
                    'id' => $roleId,
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => $systemUserId,
                    'updated_by' => $systemUserId,
                ]);
            } else {
                $roleId = DB::table('sys_roles')->where('name', $roleName)->value('id');
            }

            // Get permission IDs
            $permissionIds = DB::table('sys_permissions')
                ->whereIn('name', $roleData['permissions'])
                ->pluck('id')
                ->toArray();

            // Insert role permissions
            foreach ($permissionIds as $permissionId) {
                DB::table('sys_role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }

            $this->command->info("Created conversation role: {$roleName} with " . count($roleData['permissions']) . ' permissions.');
        }

        $this->command->info('Chat permissions and roles seeding completed!');
    }
}