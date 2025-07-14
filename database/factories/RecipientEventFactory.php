<?php

namespace Database\Factories;

use App\Models\RecipientEvent;
use App\Models\EmailRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipientEventFactory extends Factory
{
    protected $model = RecipientEvent::class;

    public function definition(): array
    {
        return [
            'recipient_id' => EmailRecipient::factory(),
            'sns_message_id' => $this->faker->uuid(),
            'type' => $this->faker->randomElement(['send', 'delivery', 'bounce', 'complaint', 'open', 'click', 'reject', 'rendering_failure']),
            'event_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'payload' => [
                'eventType' => 'test',
                'mail' => [
                    'messageId' => $this->faker->uuid(),
                    'source' => $this->faker->safeEmail(),
                    'destination' => [$this->faker->safeEmail()]
                ]
            ],
        ];
    }
}