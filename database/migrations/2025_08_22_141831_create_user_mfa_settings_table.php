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
        Schema::create('user_mfa_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->unique();
            $table->boolean('totp_enabled')->default(false);
            $table->string('totp_secret')->nullable();
            $table->timestampTz('totp_confirmed_at')->nullable();
            $table->json('backup_codes')->nullable();
            $table->integer('backup_codes_used')->default(0);
            $table->boolean('mfa_required')->default(false);
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('sys_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('user_mfa_settings');
    }
};
