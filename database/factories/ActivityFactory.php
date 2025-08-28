<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'log_name' => $this->faker->randomElement(['auth', 'organization', 'oauth', 'system', 'tenant', 'user']),
            'description' => $this->faker->sentence(),
            'subject_type' => null,
            'subject_id' => null,
            'event' => $this->faker->randomElement(['created', 'updated', 'deleted', 'login', 'logout', 'viewed']),
            'causer_type' => User::class,
            'causer_id' => User::factory(),
            'properties' => [],
            'batch_ulid' => $this->faker->ulid(),
            'organization_id' => null, // Allow null by default
            'tenant_id' => null,
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => fn (array $attributes) => $attributes['created_at'],
        ];
    }

    /**
     * Indicate that the activity has an organization context.
     */
    public function withOrganization(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => Organization::factory(),
        ]);
    }

    /**
     * Indicate that the activity is for a specific log name.
     */
    public function logName(string $logName): Factory
    {
        return $this->state(fn (array $attributes) => [
            'log_name' => $logName,
        ]);
    }

    /**
     * Indicate that the activity has a specific event.
     */
    public function event(string $event): Factory
    {
        return $this->state(fn (array $attributes) => [
            'event' => $event,
        ]);
    }

    /**
     * Indicate that the activity has a subject.
     */
    public function withSubject(string $subjectType, int $subjectId): Factory
    {
        return $this->state(fn (array $attributes) => [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
    }

    /**
     * Add properties to the activity.
     */
    public function withProperties(array $properties): Factory
    {
        return $this->state(fn (array $attributes) => [
            'properties' => array_merge($attributes['properties'] ?? [], $properties),
        ]);
    }

    /**
     * Create an authentication-related activity.
     */
    public function auth(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'log_name' => 'auth',
            'event' => $this->faker->randomElement(['login', 'logout', 'registered', 'password_reset']),
            'description' => match ($attributes['event'] ?? 'login') {
                'login' => 'User logged in',
                'logout' => 'User logged out',
                'registered' => 'User registered',
                'password_reset' => 'User reset password',
                default => 'Authentication activity',
            },
        ]);
    }

    /**
     * Create an organization-related activity.
     */
    public function organization(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'log_name' => 'organization',
            'event' => $this->faker->randomElement(['created', 'updated', 'deleted', 'member_added', 'member_removed']),
            'description' => match ($attributes['event'] ?? 'created') {
                'created' => 'Organization created',
                'updated' => 'Organization updated',
                'deleted' => 'Organization deleted',
                'member_added' => 'Member added to organization',
                'member_removed' => 'Member removed from organization',
                default => 'Organization activity',
            },
        ]);
    }

    /**
     * Create a system-related activity.
     */
    public function system(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'log_name' => 'system',
            'event' => $this->faker->randomElement(['maintenance', 'backup', 'update', 'error']),
            'description' => match ($attributes['event'] ?? 'maintenance') {
                'maintenance' => 'System maintenance performed',
                'backup' => 'System backup created',
                'update' => 'System updated',
                'error' => 'System error occurred',
                default => 'System activity',
            },
        ]);
    }
}
