<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('aggregate_ulid');
            $table->unsignedBigInteger('aggregate_version');
            $table->jsonb('state');

            $table->timestampsTz();

            $table->index('aggregate_ulid');
        });
    }
};
