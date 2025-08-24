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
            $table->string('file_path')->nullable()->after('content_hash');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('file_mime_type')->nullable()->after('file_name');
            $table->bigInteger('file_size')->nullable()->after('file_mime_type');
            $table->text('file_hash')->nullable()->after('file_size');
            $table->json('file_metadata')->nullable()->after('file_hash');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'file_path',
                'file_name',
                'file_mime_type',
                'file_size',
                'file_hash',
                'file_metadata',
            ]);
        });
    }
};
