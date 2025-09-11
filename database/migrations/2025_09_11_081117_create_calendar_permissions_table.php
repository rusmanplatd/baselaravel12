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
        Schema::create('calendar_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('calendar_id');
            $table->ulid('user_id');
            $table->enum('permission', ['read', 'write', 'admin'])->default('read');
            $table->ulid('granted_by')->nullable();
            $table->timestamps();
            
            $table->foreign('calendar_id')->references('id')->on('calendars')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('granted_by')->references('id')->on('sys_users')->onDelete('set null');
            
            $table->unique(['calendar_id', 'user_id']);
            $table->index(['user_id', 'permission']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_permissions');
    }
};
