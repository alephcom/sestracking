<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityEventTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);
        $this->project = Project::factory()->create();
    }

    private function emailWithEvent(string $subject, string $type): Email
    {
        $email = Email::factory()->create([
            'project_id' => $this->project->id,
            'subject' => $subject,
            'sent_at' => now(),
        ]);
        $recipient = EmailRecipient::factory()->create(['email_id' => $email->id]);
        RecipientEvent::factory()->create([
            'recipient_id' => $recipient->id,
            'type' => $type,
            'event_at' => now(),
        ]);

        return $email;
    }

    private function listSubjects(string $eventType): array
    {
        $response = $this->actingAs($this->user)->getJson('/activity/list/api?'.http_build_query([
            'project_id' => 'all',
            'eventType' => $eventType,
            'dateFrom' => now()->subDay()->toIso8601String(),
            'dateTo' => now()->addDay()->toIso8601String(),
        ]));

        $response->assertOk();

        return collect($response->json('rows'))->pluck('subject')->sort()->values()->all();
    }

    /** @test */
    public function list_api_filters_by_a_single_event_type(): void
    {
        $this->emailWithEvent('Bounced one', 'bounce');
        $this->emailWithEvent('Delivered one', 'delivery');

        $this->assertSame(['Bounced one'], $this->listSubjects('bounce'));
    }

    /** @test */
    public function list_api_filters_by_a_comma_separated_list_of_event_types(): void
    {
        $this->emailWithEvent('Bounced', 'bounce');
        $this->emailWithEvent('Complained', 'complaint');
        $this->emailWithEvent('Rejected', 'reject');
        $this->emailWithEvent('Render failed', 'rendering_failure');
        $this->emailWithEvent('Delivered', 'delivery');

        $this->assertSame(
            ['Bounced', 'Complained', 'Rejected', 'Render failed'],
            $this->listSubjects('bounce,complaint,reject,rendering_failure')
        );
    }

    /** @test */
    public function export_filters_by_event_type_without_error(): void
    {
        $this->emailWithEvent('Bounced', 'bounce');
        $this->emailWithEvent('Delivered', 'delivery');

        $response = $this->actingAs($this->user)->get('/activity/export?'.http_build_query([
            'project_id' => 'all',
            'eventType' => 'bounce,complaint',
            'format' => 'csv',
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Bounced', $csv);
        $this->assertStringNotContainsString('Delivered', $csv);
    }
}
