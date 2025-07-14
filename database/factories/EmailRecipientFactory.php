<?php

namespace Database\Factories;

use App\Models\EmailRecipient;
use App\Models\Email;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailRecipientFactory extends Factory
{
    protected $model = EmailRecipient::class;

    public function definition(): array
    {
        return [
            'email_id' => Email::factory(),
            'address' => $this->faker->safeEmail(),
            'status' => $this->faker->randomElement(['pending', 'delivered', 'bounced', 'complained', 'rejected']),
        ];
    }
}