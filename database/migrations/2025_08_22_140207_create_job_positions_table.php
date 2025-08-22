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
        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_level_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('responsibilities')->nullable();
            $table->integer('openings')->default(1);
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship'])->default('full_time');
            $table->enum('status', ['open', 'closed', 'on_hold'])->default('open');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_positions');
    }
};
