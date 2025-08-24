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
        // Add privacy controls for reactions
        Schema::table('chat_message_reactions', function (Blueprint $table) {
            $table->boolean('is_anonymous')->default(false)->after('emoji');
            $table->json('metadata')->nullable()->after('is_anonymous');
        });

        // Add privacy controls for read receipts
        Schema::table('chat_message_read_receipts', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('read_at');
            $table->json('metadata')->nullable()->after('is_private');
        });

        // Add conversation-level privacy settings
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->json('privacy_settings')->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_message_reactions', function (Blueprint $table) {
            $table->dropColumn(['is_anonymous', 'metadata']);
        });

        Schema::table('chat_message_read_receipts', function (Blueprint $table) {
            $table->dropColumn(['is_private', 'metadata']);
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn('privacy_settings');
        });
    }
};
