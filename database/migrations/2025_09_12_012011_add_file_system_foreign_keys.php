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
        // Add foreign key constraints for self-referencing tables
        Schema::table('folders', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('folders')->onDelete('cascade');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->foreign('parent_file_id')->references('id')->on('files')->onDelete('set null');
        });

        // Add user foreign key constraints
        Schema::table('file_shares', function (Blueprint $table) {
            $table->foreign('shared_by')->references('id')->on('sys_users')->onDelete('cascade');
        });

        Schema::table('file_access_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('set null');
        });

        Schema::table('file_tag_assignments', function (Blueprint $table) {
            $table->foreign('file_tag_id')->references('id')->on('file_tags')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('sys_users')->onDelete('cascade');
        });

        Schema::table('file_comments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('file_comments')->onDelete('cascade');
        });

        Schema::table('file_permissions', function (Blueprint $table) {
            $table->foreign('granted_by')->references('id')->on('sys_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('file_permissions', function (Blueprint $table) {
            $table->dropForeign(['granted_by']);
        });

        Schema::table('file_comments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['parent_id']);
        });

        Schema::table('file_tag_assignments', function (Blueprint $table) {
            $table->dropForeign(['file_tag_id']);
            $table->dropForeign(['assigned_by']);
        });

        Schema::table('file_access_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('file_shares', function (Blueprint $table) {
            $table->dropForeign(['shared_by']);
        });

        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['parent_file_id']);
        });

        Schema::table('folders', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
    }
};