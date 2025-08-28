<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('aggregate_ulid')->nullable();
            $table->unsignedBigInteger('aggregate_version')->nullable();
            $table->unsignedTinyInteger('event_version')->default(1);
            $table->string('event_class');
            $table->jsonb('event_properties');
            $table->jsonb('meta_data');
            $table->timestampTz('created_at');
            $table->index('event_class');
            $table->index('aggregate_ulid');

            $table->unique(['aggregate_ulid', 'aggregate_version']);
        });
    }
};
