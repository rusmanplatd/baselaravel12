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
            // Add encrypted fields for voice message data
            $table->longText('encrypted_voice_transcript')->nullable()->after('voice_transcript');
            $table->text('encrypted_voice_waveform_data')->nullable()->after('voice_waveform_data');
            $table->string('voice_transcript_hash')->nullable()->after('encrypted_voice_transcript');
            $table->string('voice_waveform_hash')->nullable()->after('encrypted_voice_waveform_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'encrypted_voice_transcript',
                'encrypted_voice_waveform_data',
                'voice_transcript_hash',
                'voice_waveform_hash',
            ]);
        });
    }
};
