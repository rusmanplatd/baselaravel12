<?php

namespace Database\Factories;

use App\Models\Chat\Conversation;
use App\Models\ScheduledMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledMessageFactory extends Factory
{
    protected $model = ScheduledMessage::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['scheduled', 'sending', 'sent', 'failed', 'cancelled']);
        
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'content' => $this->faker->sentence(),
            'content_type' => $this->faker->randomElement(['text', 'markdown']),
            'scheduled_for' => $this->faker->dateTimeBetween('+1 hour', '+7 days'),
            'timezone' => $this->faker->randomElement(['UTC', 'America/New_York', 'Europe/London', 'Asia/Tokyo']),
            'status' => $status,
            'retry_count' => $status === 'failed' ? $this->faker->numberBetween(1, 3) : 0,
            'max_retries' => $this->faker->numberBetween(3, 5),
            'error_message' => $status === 'failed' ? $this->faker->sentence() : null,
            'sent_message_id' => $status === 'sent' ? $this->faker->uuid() : null,
            'sent_at' => $status === 'sent' ? $this->faker->dateTimeBetween('-1 day', 'now') : null,
            'cancelled_at' => $status === 'cancelled' ? $this->faker->dateTimeBetween('-1 day', 'now') : null,
            'metadata' => $this->faker->optional()->passthrough([
                'recurring' => $this->faker->boolean(),
                'priority' => $this->faker->randomElement(['low', 'normal', 'high']),
                'notification_settings' => [
                    'send_confirmation' => $this->faker->boolean(),
                    'send_reminders' => $this->faker->boolean(),
                ]
            ]),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'sent_message_id' => null,
            'sent_at' => null,
            'cancelled_at' => null,
            'error_message' => null,
            'retry_count' => 0,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_message_id' => $this->faker->uuid(),
            'sent_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $this->faker->randomElement([
                'Failed to send message to conversation',
                'User does not have permission to send messages',
                'Conversation is archived or deleted',
                'Message content validation failed'
            ]),
            'retry_count' => $this->faker->numberBetween(1, 3),
            'sent_message_id' => null,
            'sent_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'sent_message_id' => null,
            'sent_at' => null,
        ]);
    }

    public function sending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sending',
            'sent_message_id' => null,
            'sent_at' => null,
            'error_message' => null,
        ]);
    }

    public function readyToSend(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_for' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'error_message' => null,
            'retry_count' => 0,
        ]);
    }

    public function futureScheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_for' => $this->faker->dateTimeBetween('+1 hour', '+7 days'),
        ]);
    }

    public function overdueMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_for' => $this->faker->dateTimeBetween('-2 hours', '-30 minutes'),
        ]);
    }

    public function retryableMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'retry_count' => 1,
            'max_retries' => 3,
            'error_message' => 'Temporary network error',
        ]);
    }

    public function maxRetriesReached(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'retry_count' => 3,
            'max_retries' => 3,
            'error_message' => 'Maximum retries reached',
        ]);
    }

    public function withTimezone(string $timezone): static
    {
        return $this->state(fn (array $attributes) => [
            'timezone' => $timezone,
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'recurring' => true,
                'recurrence_pattern' => $this->faker->randomElement(['daily', 'weekly', 'monthly']),
                'recurrence_end' => $this->faker->dateTimeBetween('+1 month', '+6 months'),
            ]),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'priority' => 'high',
            ]),
        ]);
    }

    public function withNotifications(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'notification_settings' => [
                    'send_confirmation' => true,
                    'send_reminders' => true,
                    'reminder_intervals' => [60, 30, 5], // minutes before scheduled time
                ],
            ]),
        ]);
    }
}