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
        Schema::create('calendars', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#3498db'); // Hex color
            $table->string('timezone')->default('UTC');
            $table->ulid('calendarable_id');
            $table->string('calendarable_type');
            $table->enum('visibility', ['public', 'private', 'shared'])->default('private');
            $table->json('settings')->nullable(); // Additional calendar settings
            $table->boolean('is_active')->default(true);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('sys_users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('sys_users')->onDelete('set null');
            
            $table->index(['calendarable_type', 'calendarable_id'], 'calendars_owner_index');
            $table->index(['is_active', 'visibility']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};
