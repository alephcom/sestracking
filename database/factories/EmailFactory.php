<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailFactory extends Factory
{
    protected $model = Email::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'message_id' => $this->faker->uuid(),
            'source' => $this->faker->safeEmail(),
            'subject' => $this->faker->sentence(6),
            'sent_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'opens' => $this->faker->numberBetween(0, 10),
            'clicks' => $this->faker->numberBetween(0, 5),
        ];
    }
}