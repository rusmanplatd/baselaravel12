<?php

namespace Database\Factories;

use App\Models\Bot;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BotFactory extends Factory
{
    protected $model = Bot::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Bot',
            'description' => $this->faker->sentence(),
            'avatar' => $this->faker->optional()->imageUrl(150, 150, 'cats'),
            'api_token' => 'bot_' . Str::random(64),
            'webhook_url' => $this->faker->optional()->url(),
            'webhook_secret' => $this->faker->optional()->regexify('whsec_[A-Za-z0-9]{32}'),
            'is_active' => true,
            'capabilities' => $this->faker->randomElements([
                'receive_messages',
                'send_messages',
                'read_history',
                'process_files',
                'quantum_encryption',
                'manage_conversation',
                'create_polls',
                'schedule_messages',
                'auto_respond',
                'sentiment_analysis',
                'language_translation',
                'message_moderation'
            ], $this->faker->numberBetween(2, 6)),
            'configuration' => [
                'encryption' => [
                    'preferred_algorithm' => $this->faker->randomElement(['ML-KEM-768', 'RSA-4096-OAEP']),
                ],
                'auto_response' => [
                    'enabled' => $this->faker->boolean(),
                    'delay_seconds' => $this->faker->numberBetween(1, 60),
                ],
                'language' => $this->faker->randomElement(['en', 'es', 'fr', 'de']),
            ],
            'rate_limit_per_minute' => $this->faker->numberBetween(10, 300),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
        ];
    }

    public function quantumCapable(): static
    {
        return $this->state(fn (array $attributes) => [
            'capabilities' => array_merge($attributes['capabilities'] ?? [], ['quantum_encryption']),
            'configuration' => array_merge($attributes['configuration'] ?? [], [
                'encryption' => ['preferred_algorithm' => 'ML-KEM-768']
            ])
        ]);
    }

    public function withWebhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_url' => $this->faker->url(),
            'webhook_secret' => 'whsec_' . Str::random(32),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'capabilities' => ['receive_messages', 'send_messages'],
            'configuration' => [
                'language' => 'en',
                'auto_response' => ['enabled' => false]
            ],
            'rate_limit_per_minute' => 60,
        ]);
    }
}