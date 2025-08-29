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
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('encryption_algorithm')->default('AES-256-GCM')->after('metadata');
            $table->integer('key_strength')->default(256)->after('encryption_algorithm');
            $table->json('encryption_info')->nullable()->after('key_strength');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['encryption_algorithm', 'key_strength', 'encryption_info']);
        });
    }
};
