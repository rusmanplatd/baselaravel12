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
            $table->integer('voice_duration_seconds')->nullable()->after('content_hash');
            $table->text('voice_transcript')->nullable()->after('voice_duration_seconds');
            $table->string('voice_waveform_data', 1000)->nullable()->after('voice_transcript');
            $table->timestampTz('scheduled_at')->nullable()->after('voice_waveform_data');
            $table->enum('message_priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->after('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'voice_duration_seconds',
                'voice_transcript',
                'voice_waveform_data',
                'scheduled_at',
                'message_priority',
            ]);
        });
    }
};
