<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'token' => Str::random(32),
        ];
    }

    /**
     * Accept unsigned webhook requests (for tests that post raw payloads rather than signed SNS messages).
     */
    public function allowingUnsignedWebhooks(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_unsigned_webhooks' => true,
        ]);
    }
}