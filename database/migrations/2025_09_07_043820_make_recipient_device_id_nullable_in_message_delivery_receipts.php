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
        Schema::table('message_delivery_receipts', function (Blueprint $table) {
            // Make recipient_device_id nullable for E2EE messages
            $table->foreignUlid('recipient_device_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_delivery_receipts', function (Blueprint $table) {
            // Revert back to NOT NULL
            $table->foreignUlid('recipient_device_id')->nullable(false)->change();
        });
    }
};
