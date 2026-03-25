<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsUnsubscribesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function emails_report_includes_unsubscribe_counts(): void
    {
        $admin = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);
        $project = Project::factory()->create();
        $email = Email::factory()->create([
            'project_id' => $project->id,
            'sent_at' => Carbon::parse('2025-06-15 12:00:00'),
        ]);
        $recipient = EmailRecipient::factory()->create(['email_id' => $email->id]);
        RecipientEvent::factory()->create([
            'recipient_id' => $recipient->id,
            'type' => 'subscription',
            'event_at' => Carbon::parse('2025-06-16 10:00:00'),
        ]);

        $this->actingAs($admin)
            ->getJson(route('reports.emails', [
                'projectId' => (string) $project->id,
                'dateFrom' => '2025-06-01',
                'dateTo' => '2025-06-30',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.unsubscribes', 1);
    }

    /** @test */
    public function recipients_report_includes_unsubscribe_totals_per_address(): void
    {
        $admin = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);
        $project = Project::factory()->create();
        $email = Email::factory()->create([
            'project_id' => $project->id,
            'sent_at' => Carbon::parse('2025-06-15 12:00:00'),
        ]);
        $recipient = EmailRecipient::factory()->create([
            'email_id' => $email->id,
            'address' => 'reader@example.com',
        ]);
        RecipientEvent::factory()->create([
            'recipient_id' => $recipient->id,
            'type' => 'subscription',
            'event_at' => Carbon::parse('2025-06-16 10:00:00'),
        ]);

        $this->actingAs($admin)
            ->getJson(route('reports.recipients', [
                'projectId' => (string) $project->id,
                'dateFrom' => '2025-06-01',
                'dateTo' => '2025-06-30',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.address', 'reader@example.com')
            ->assertJsonPath('data.0.total_unsubscribes', 1);
    }

    /** @test */
    public function unsubscribes_report_filters_by_subscription_event_date(): void
    {
        $admin = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);
        $project = Project::factory()->create();
        $email = Email::factory()->create([
            'project_id' => $project->id,
            'subject' => 'List mail',
            'sent_at' => Carbon::parse('2025-05-01 12:00:00'),
        ]);
        $recipient = EmailRecipient::factory()->create([
            'email_id' => $email->id,
            'address' => 'leave@example.com',
        ]);
        RecipientEvent::factory()->create([
            'recipient_id' => $recipient->id,
            'type' => 'subscription',
            'event_at' => Carbon::parse('2025-06-20 15:00:00'),
            'payload' => [
                'subscription' => [
                    'contactList' => 'MyList',
                    'source' => 'UnsubscribeHeader',
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->getJson(route('reports.unsubscribes', [
                'projectId' => (string) $project->id,
                'dateFrom' => '2025-06-01',
                'dateTo' => '2025-06-30',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient_address', 'leave@example.com')
            ->assertJsonPath('data.0.email_subject', 'List mail')
            ->assertJsonPath('data.0.contact_list', 'MyList')
            ->assertJsonPath('data.0.subscription_source', 'UnsubscribeHeader');
    }
}
