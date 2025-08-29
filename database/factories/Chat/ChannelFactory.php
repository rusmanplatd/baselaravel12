<?php

namespace Database\Factories\Chat;

use App\Models\Chat\Channel;
use App\Models\Chat\Conversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat\Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        
        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->optional(0.7)->sentence(),
            'visibility' => $this->faker->randomElement(['public', 'private']),
            'avatar_url' => $this->faker->optional(0.3)->imageUrl(200, 200, 'avatars'),
            'metadata' => $this->faker->optional(0.3)->randomElements([
                'featured' => $this->faker->boolean,
                'category' => $this->faker->word,
                'tags' => $this->faker->words(3),
            ]),
            'status' => 'active',
            'conversation_id' => function (array $attributes) {
                return Conversation::factory()->create([
                    'name' => $attributes['name'],
                    'type' => 'group',
                    'description' => $attributes['description'] ?? null,
                    'created_by' => $attributes['created_by'] ?? User::factory(),
                ])->id;
            },
            'organization_id' => $this->faker->optional(0.5)->randomElement([
                null,
                Organization::factory(),
            ]),
            'created_by' => User::factory(),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    public function withOrganization(Organization $organization = null): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization?->id ?? Organization::factory(),
        ]);
    }

    public function withoutOrganization(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deleted',
            'deleted_at' => now(),
        ]);
    }
}