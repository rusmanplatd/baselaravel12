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
            $table->boolean('is_encrypted')->default(false)->after('metadata');
            $table->string('encryption_algorithm')->nullable()->after('is_encrypted');
            $table->integer('key_strength')->nullable()->after('encryption_algorithm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['is_encrypted', 'encryption_algorithm', 'key_strength']);
        });
    }
};
