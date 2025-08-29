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
        Schema::create('oauth_device_codes', function (Blueprint $table) {
            // Primary key - use device_code as primary key (Google-like)
            $table->string('device_code')->primary();
            
            // Core device flow fields
            $table->string('user_code')->unique();
            $table->ulid('client_id');
            $table->ulid('user_id')->nullable();
            $table->json('scopes')->nullable();
            
            // Device flow specific fields (Google-like)
            $table->timestamp('expires_at');
            $table->timestamp('last_polled_at')->nullable();
            $table->integer('poll_count')->default(0);
            $table->enum('status', ['pending', 'authorized', 'denied', 'expired'])->default('pending');
            $table->string('verification_uri');
            $table->string('verification_uri_complete')->nullable();
            $table->integer('interval')->default(5);
            
            // Standard timestamps
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');

            // Performance indexes
            $table->index(['user_code', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('oauth_device_codes');
    }

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
