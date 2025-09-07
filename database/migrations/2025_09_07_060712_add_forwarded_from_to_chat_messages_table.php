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
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignUlid('forwarded_from_id')->nullable()->after('reply_to_id');
            $table->foreignUlid('original_conversation_id')->nullable()->after('forwarded_from_id');
            $table->integer('forward_count')->default(0)->after('original_conversation_id');
            
            $table->foreign('forwarded_from_id')->references('id')->on('chat_messages')->onDelete('set null');
            $table->foreign('original_conversation_id')->references('id')->on('chat_conversations')->onDelete('set null');
            
            $table->index(['forwarded_from_id']);
            $table->index(['original_conversation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['forwarded_from_id']);
            $table->dropForeign(['original_conversation_id']);
            $table->dropColumn(['forwarded_from_id', 'original_conversation_id', 'forward_count']);
        });
    }
};
