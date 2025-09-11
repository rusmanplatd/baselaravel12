<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_item_assignees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_item_id')->constrained('project_items')->onDelete('cascade');
            $table->foreignUlid('user_id')->constrained('sys_users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['project_item_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_item_assignees');
    }
};