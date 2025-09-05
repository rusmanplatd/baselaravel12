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
        // Polls table
        Schema::create('polls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->foreignUlid('creator_id')->constrained('sys_users')->onDelete('cascade');
            $table->string('poll_type')->default('single_choice'); // single_choice, multiple_choice, rating
            $table->text('encrypted_question');
            $table->string('question_hash');
            $table->json('encrypted_options'); // Array of encrypted options
            $table->json('option_hashes'); // Array of option hashes for integrity
            $table->boolean('anonymous')->default(false);
            $table->boolean('allow_multiple_votes')->default(false);
            $table->boolean('show_results_immediately')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->json('settings')->nullable(); // Additional poll settings
            $table->timestamps();

            $table->index(['message_id', 'created_at']);
            $table->index(['creator_id', 'created_at']);
            $table->index(['expires_at', 'is_closed']);
        });

        // Poll options table (for more complex polls)
        Schema::create('poll_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('poll_id')->constrained('polls')->onDelete('cascade');
            $table->integer('option_order');
            $table->text('encrypted_option_text');
            $table->string('option_hash');
            $table->string('option_type')->default('text'); // text, image, emoji
            $table->text('encrypted_metadata')->nullable(); // For images, emojis, etc.
            $table->timestamps();

            $table->unique(['poll_id', 'option_order']);
            $table->index(['poll_id', 'option_order']);
        });

        // Poll votes table (encrypted individual responses)
        Schema::create('poll_votes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('poll_id')->constrained('polls')->onDelete('cascade');
            $table->foreignUlid('voter_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('device_id')->constrained('user_devices')->onDelete('cascade');
            $table->json('encrypted_vote_data'); // Encrypted vote choices
            $table->string('vote_hash'); // Hash for integrity verification
            $table->json('vote_encryption_keys'); // Per-device encrypted vote keys
            $table->boolean('is_anonymous')->default(false);
            $table->text('encrypted_reasoning')->nullable(); // Optional reasoning for choice
            $table->timestamp('voted_at');
            $table->timestamps();

            $table->unique(['poll_id', 'voter_id']); // One vote per user per poll
            $table->index(['poll_id', 'voted_at']);
            $table->index(['voter_id', 'voted_at']);
        });

        // Survey table (for more complex questionnaires)
        Schema::create('surveys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->foreignUlid('creator_id')->constrained('sys_users')->onDelete('cascade');
            $table->text('encrypted_title');
            $table->string('title_hash');
            $table->text('encrypted_description')->nullable();
            $table->string('description_hash')->nullable();
            $table->boolean('anonymous')->default(false);
            $table->boolean('allow_partial_responses')->default(true);
            $table->boolean('randomize_questions')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'created_at']);
            $table->index(['creator_id', 'created_at']);
            $table->index(['expires_at', 'is_closed']);
        });

        // Survey questions table
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('survey_id')->constrained('surveys')->onDelete('cascade');
            $table->integer('question_order');
            $table->string('question_type'); // text, multiple_choice, rating, date, etc.
            $table->text('encrypted_question_text');
            $table->string('question_hash');
            $table->boolean('required')->default(false);
            $table->json('encrypted_options')->nullable(); // For multiple choice questions
            $table->json('option_hashes')->nullable();
            $table->json('validation_rules')->nullable(); // Min/max length, regex, etc.
            $table->json('settings')->nullable(); // Question-specific settings
            $table->timestamps();

            $table->unique(['survey_id', 'question_order']);
            $table->index(['survey_id', 'question_order']);
        });

        // Survey responses table
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('survey_id')->constrained('surveys')->onDelete('cascade');
            $table->foreignUlid('respondent_id')->constrained('sys_users')->onDelete('cascade');
            $table->foreignUlid('device_id')->constrained('user_devices')->onDelete('cascade');
            $table->boolean('is_complete')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('response_encryption_keys'); // Per-device encrypted response keys
            $table->timestamps();

            $table->unique(['survey_id', 'respondent_id']); // One response per user per survey
            $table->index(['survey_id', 'completed_at']);
            $table->index(['respondent_id', 'started_at']);
        });

        // Survey question responses table
        Schema::create('survey_question_responses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('survey_response_id')->constrained('survey_responses')->onDelete('cascade');
            $table->foreignUlid('question_id')->constrained('survey_questions')->onDelete('cascade');
            $table->text('encrypted_answer');
            $table->string('answer_hash');
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->unique(['survey_response_id', 'question_id']);
            $table->index(['question_id', 'answered_at']);
        });

        // Poll and survey analytics (aggregated, non-identifying data)
        Schema::create('poll_analytics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('poll_id')->constrained('polls')->onDelete('cascade');
            $table->json('encrypted_results_summary'); // Aggregated vote counts, no individual data
            $table->json('participation_stats'); // Total votes, completion rate, etc.
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['poll_id', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poll_analytics');
        Schema::dropIfExists('survey_question_responses');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
    }
};
