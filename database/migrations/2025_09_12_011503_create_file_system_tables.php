<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create folders table with polymorphic ownership
        Schema::create('folders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable(); // Hex color for folder
            
            // Polymorphic ownership (user, organization, project, etc.)
            $table->string('owner_type');
            $table->string('owner_id');
            $table->index(['owner_type', 'owner_id']);
            
            // Parent folder for nested structure
            $table->ulid('parent_id')->nullable();
            
            // Path for efficient querying
            $table->string('path')->nullable();
            $table->integer('level')->default(0);
            
            // Permissions and sharing
            $table->enum('visibility', ['private', 'internal', 'public'])->default('private');
            $table->boolean('is_shared')->default(false);
            $table->json('share_settings')->nullable();
            
            // Metadata
            $table->bigInteger('total_size')->default(0);
            $table->integer('file_count')->default(0);
            $table->integer('folder_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['owner_type', 'owner_id', 'parent_id']);
            $table->index('path');
            $table->index(['visibility', 'is_shared']);
        });

        // Create files table with polymorphic ownership
        Schema::create('files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('original_name');
            $table->text('description')->nullable();
            
            // Polymorphic ownership (user, organization, project, etc.)
            $table->string('owner_type');
            $table->string('owner_id');
            $table->index(['owner_type', 'owner_id']);
            
            // Folder relationship
            $table->foreignUlid('folder_id')->nullable()->constrained('folders')->onDelete('set null');
            
            // File information
            $table->string('mime_type');
            $table->string('extension', 10);
            $table->bigInteger('size');
            $table->string('hash')->unique(); // For deduplication
            
            // Storage information
            $table->string('disk')->default('minio');
            $table->string('path'); // Storage path
            $table->boolean('is_encrypted')->default(false);
            $table->json('encryption_metadata')->nullable();
            
            // Thumbnails and previews
            $table->string('thumbnail_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->boolean('has_preview')->default(false);
            
            // Permissions and sharing
            $table->enum('visibility', ['private', 'internal', 'public'])->default('private');
            $table->boolean('is_shared')->default(false);
            $table->json('share_settings')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable(); // EXIF, dimensions, etc.
            $table->integer('download_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            
            // Versioning
            $table->string('version', 20)->default('1.0');
            $table->ulid('parent_file_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['owner_type', 'owner_id', 'folder_id']);
            $table->index(['mime_type', 'extension']);
            $table->index(['visibility', 'is_shared']);
            $table->index('hash');
        });

        // Create file shares table for granular sharing
        Schema::create('file_shares', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // What is being shared (file or folder)
            $table->string('shareable_type');
            $table->string('shareable_id');
            $table->index(['shareable_type', 'shareable_id']);
            
            // Who is sharing (always a user)
            $table->ulid('shared_by');
            
            // Who it's shared with (polymorphic - user, organization, public)
            $table->string('shared_with_type')->nullable();
            $table->string('shared_with_id')->nullable();
            $table->index(['shared_with_type', 'shared_with_id']);
            
            // Share type and permissions
            $table->enum('share_type', ['link', 'direct', 'public'])->default('direct');
            $table->string('share_token')->nullable()->unique(); // For link sharing
            $table->json('permissions'); // ['read', 'write', 'delete', 'share']
            
            // Access control
            $table->string('password')->nullable(); // Hashed password for protected links
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_downloads')->nullable();
            $table->integer('download_count')->default(0);
            
            // Metadata
            $table->boolean('notify_on_access')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['share_type', 'share_token']);
            $table->index(['is_active', 'expires_at']);
        });

        // Create file access logs for audit trail
        Schema::create('file_access_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // What was accessed
            $table->string('file_type'); // 'file' or 'folder'
            $table->string('file_id');
            $table->index(['file_type', 'file_id']);
            
            // Who accessed it
            $table->ulid('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            // Access details
            $table->enum('action', ['view', 'download', 'upload', 'delete', 'share', 'copy', 'move']);
            $table->string('share_token')->nullable(); // If accessed via share link
            $table->json('metadata')->nullable(); // Additional context
            
            $table->timestamp('accessed_at');
            $table->timestamps();
            
            $table->index(['user_id', 'accessed_at']);
            $table->index(['action', 'accessed_at']);
        });

        // Create file tags table
        Schema::create('file_tags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->nullable();
            $table->text('description')->nullable();
            
            // Scoped to owner
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();
            $table->index(['owner_type', 'owner_id']);
            
            $table->timestamps();
        });

        // Pivot table for file-tag relationships
        Schema::create('file_tag_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('taggable_type'); // 'file' or 'folder'
            $table->string('taggable_id');
            $table->ulid('file_tag_id');
            $table->ulid('assigned_by');
            $table->timestamps();
            
            $table->unique(['taggable_type', 'taggable_id', 'file_tag_id'], 'file_tag_unique');
            $table->index(['taggable_type', 'taggable_id']);
        });

        // Create file comments table
        Schema::create('file_comments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // What is being commented on
            $table->string('commentable_type'); // 'file' or 'folder'
            $table->string('commentable_id');
            $table->index(['commentable_type', 'commentable_id']);
            
            // Comment details
            $table->ulid('user_id');
            $table->text('content');
            $table->json('metadata')->nullable(); // Mentions, attachments, etc.
            
            // Threading
            $table->ulid('parent_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'created_at']);
        });

        // File system permissions table for granular access control
        Schema::create('file_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // What the permission applies to
            $table->string('permissionable_type'); // 'file' or 'folder'
            $table->string('permissionable_id');
            $table->index(['permissionable_type', 'permissionable_id']);
            
            // Who has the permission (polymorphic - user, role, organization)
            $table->string('subject_type');
            $table->string('subject_id');
            $table->index(['subject_type', 'subject_id']);
            
            // Permission details
            $table->json('permissions'); // ['read', 'write', 'delete', 'share', 'comment']
            $table->boolean('inherited')->default(false); // Inherited from parent folder
            $table->ulid('granted_by');
            
            $table->timestamps();
            
            $table->unique(['permissionable_type', 'permissionable_id', 'subject_type', 'subject_id'], 'file_permission_unique');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_permissions');
        Schema::dropIfExists('file_comments');
        Schema::dropIfExists('file_tag_assignments');
        Schema::dropIfExists('file_tags');
        Schema::dropIfExists('file_access_logs');
        Schema::dropIfExists('file_shares');
        Schema::dropIfExists('files');
        Schema::dropIfExists('folders');
    }
};