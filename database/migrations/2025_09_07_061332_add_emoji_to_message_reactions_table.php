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
        Schema::table('message_reactions', function (Blueprint $table) {
            $table->string('emoji')->after('reaction_type');
            
            $table->index(['message_id', 'emoji']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_reactions', function (Blueprint $table) {
            $table->dropIndex(['message_id', 'emoji']);
            $table->dropColumn('emoji');
        });
    }
};
