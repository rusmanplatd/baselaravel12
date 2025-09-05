<?php

namespace Database\Factories;

use App\Models\Chat\Message;
use App\Models\Chat\MessageFile;
use App\Models\VoiceTranscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoiceTranscriptionFactory extends Factory
{
    protected $model = VoiceTranscription::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']);
        
        return [
            'message_id' => Message::factory(),
            'attachment_id' => MessageFile::factory(),
            'transcript' => $status === 'completed' ? $this->faker->sentences(3, true) : null,
            'language' => $this->faker->randomElement(['en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'ja', 'ko', 'zh']),
            'confidence' => $status === 'completed' ? $this->faker->randomFloat(2, 60, 99) : null,
            'duration' => $this->faker->randomFloat(1, 5, 300), // 5 seconds to 5 minutes
            'word_count' => $status === 'completed' ? $this->faker->numberBetween(10, 200) : null,
            'segments' => $status === 'completed' ? $this->generateMockSegments() : null,
            'status' => $status,
            'provider' => $this->faker->randomElement(['openai-whisper', 'local-whisper']),
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
            'retry_count' => $status === 'failed' ? $this->faker->numberBetween(1, 3) : 0,
            'processed_at' => in_array($status, ['completed', 'failed']) ? $this->faker->dateTimeBetween('-1 hour', 'now') : null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'transcript' => $this->faker->sentences(5, true),
            'confidence' => $this->faker->randomFloat(2, 75, 98),
            'word_count' => $this->faker->numberBetween(20, 150),
            'segments' => $this->generateMockSegments(),
            'processed_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'transcript' => null,
            'confidence' => null,
            'word_count' => null,
            'segments' => null,
            'error_message' => $this->faker->randomElement([
                'Audio file format not supported',
                'OpenAI API rate limit exceeded',
                'Network timeout during transcription',
                'Audio file corrupted or empty'
            ]),
            'retry_count' => $this->faker->numberBetween(1, 3),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'transcript' => null,
            'confidence' => null,
            'word_count' => null,
            'segments' => null,
            'processed_at' => null,
            'error_message' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'transcript' => null,
            'confidence' => null,
            'word_count' => null,
            'segments' => null,
            'processed_at' => null,
            'error_message' => null,
        ]);
    }

    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => $this->faker->randomFloat(2, 90, 99),
        ]);
    }

    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => $this->faker->randomFloat(2, 40, 70),
        ]);
    }

    public function english(): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => 'en',
            'transcript' => $this->faker->paragraph(),
        ]);
    }

    public function spanish(): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => 'es',
            'transcript' => 'Hola, este es un mensaje de voz en español para pruebas.',
        ]);
    }

    public function longDuration(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => $this->faker->randomFloat(1, 120, 600), // 2-10 minutes
            'word_count' => $this->faker->numberBetween(200, 800),
        ]);
    }

    public function shortDuration(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => $this->faker->randomFloat(1, 1, 30), // 1-30 seconds
            'word_count' => $this->faker->numberBetween(5, 50),
        ]);
    }

    private function generateMockSegments(): array
    {
        $segmentCount = $this->faker->numberBetween(2, 8);
        $segments = [];
        $currentTime = 0;

        for ($i = 0; $i < $segmentCount; $i++) {
            $duration = $this->faker->randomFloat(1, 2, 15);
            $endTime = $currentTime + $duration;
            
            $segments[] = [
                'id' => $i,
                'start' => round($currentTime, 1),
                'end' => round($endTime, 1),
                'text' => $this->faker->sentence(),
                'avg_logprob' => $this->faker->randomFloat(3, -1.0, -0.1),
                'compression_ratio' => $this->faker->randomFloat(2, 1.0, 2.5),
                'no_speech_prob' => $this->faker->randomFloat(3, 0.001, 0.1),
            ];
            
            $currentTime = $endTime;
        }

        return $segments;
    }
}