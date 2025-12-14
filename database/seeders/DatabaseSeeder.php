<?php

namespace Database\Seeders;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create super admin user
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'super_admin' => true,
        ]);

        // Create 10 projects with sample data
        Project::factory(10)->create()->each(function ($project) {

            // Create 2 users for each project (1 admin, 1 regular user)
            $projectAdmin = User::factory()->create();
            $projectUser = User::factory()->create();
            
            // Attach users to project with appropriate roles
            $project->users()->attach($projectAdmin, ['role' => 'admin']);
            $project->users()->attach($projectUser, ['role' => 'user']);

            // Create 200 emails for each project
            Email::factory(200)->create([
                'project_id' => $project->id,
            ])->each(function ($email) {
                // Create 1-3 recipients for each email
                $recipientCount = rand(1, 3);
                EmailRecipient::factory($recipientCount)->create([
                    'email_id' => $email->id,
                ])->each(function ($recipient) use ($email) {
                    // Create events for each recipient based on status
                    $eventCount = rand(1, 4);
                    $eventTypes = $this->getEventTypesForStatus($recipient->status);
                    
                    for ($i = 0; $i < $eventCount; $i++) {
                        RecipientEvent::factory()->create([
                            'recipient_id' => $recipient->id,
                            'type' => $eventTypes[array_rand($eventTypes)],
                            'event_at' => fake()->dateTimeBetween($email->sent_at, 'now'),
                        ]);
                    }
                });
            });
        });
    }

    /**
     * Get appropriate event types based on recipient status
     */
    private function getEventTypesForStatus(string $status): array
    {
        return match ($status) {
            'delivered' => ['send', 'delivery', 'open', 'click'],
            'bounced' => ['send', 'bounce'],
            'rejected' => ['send', 'reject'],
            'complained' => ['send', 'delivery', 'complaint'],
            default => ['send'],
        };
    }
}
