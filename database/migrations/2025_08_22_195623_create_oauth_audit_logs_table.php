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
        Schema::create('oauth_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // authorize, token, userinfo, revoke, etc.
            $table->ulid('client_id')->nullable();
            $table->char('user_id', 26)->nullable();
            $table->ipAddress('ip_address');
            $table->string('user_agent')->nullable();
            $table->json('scopes')->nullable();
            $table->string('grant_type')->nullable();
            $table->boolean('success')->default(true);
            $table->string('error_code')->nullable();
            $table->text('error_description')->nullable();
            $table->json('metadata')->nullable(); // Additional contextual data
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('oauth_clients')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');

            $table->index(['event_type', 'created_at']);
            $table->index(['client_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['success', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_audit_logs');
    }
};
