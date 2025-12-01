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
        Schema::create('bots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->string('api_token', 100)->unique();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('capabilities')->nullable();
            $table->json('configuration')->nullable();
            $table->integer('rate_limit_per_minute')->default(60);
            $table->ulid('organization_id');
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('sys_users')->onDelete('cascade');
            
            $table->index(['organization_id', 'is_active']);
            $table->index('api_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};