<?php

namespace Tests\Unit;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function status_append_returns_bounced_when_any_recipient_is_bounced(): void
    {
        $project = Project::factory()->create();
        $email = Email::factory()->create(['project_id' => $project->id]);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'status' => 'delivered']);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'status' => 'bounced']);

        $email->load('recipients');
        $this->assertEquals('bounced', $email->status);
    }

    /** @test */
    public function status_append_returns_bounced_when_any_recipient_is_rejected(): void
    {
        $project = Project::factory()->create();
        $email = Email::factory()->create(['project_id' => $project->id]);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'status' => 'rejected']);

        $email->load('recipients');
        $this->assertEquals('bounced', $email->status);
    }

    /** @test */
    public function status_append_returns_complained_when_any_recipient_complained_and_none_bounced(): void
    {
        $project = Project::factory()->create();
        $email = Email::factory()->create(['project_id' => $project->id]);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'status' => 'delivered']);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'status' => 'complained']);

        $email->load('recipients');
        $this->assertEquals('complained', $email->status);
    }

    /** @test */
    public function status_append_returns_delivered_when_any_recipient_delivered(): void
    {
        $project = Project::factory()->create();
        $email = Email::factory()->create(['project_id' => $project->id]);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'status' => 'delivered']);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'status' => 'pending']);

        $email->load('recipients');
        $this->assertEquals('delivered', $email->status);
    }

    /** @test */
    public function status_append_returns_sent_when_no_delivery_bounce_or_complaint(): void
    {
        $project = Project::factory()->create();
        $email = Email::factory()->create(['project_id' => $project->id]);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'status' => 'pending']);

        $email->load('recipients');
        $this->assertEquals('sent', $email->status);
    }

    /** @test */
    public function destination_append_returns_recipient_addresses(): void
    {
        $project = Project::factory()->create();
        $email = Email::factory()->create(['project_id' => $project->id]);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'address' => 'one@example.com']);
        EmailRecipient::factory()->create(['email_id' => $email->id, 'address' => 'two@example.com']);

        $email->load('recipients');
        $destinations = $email->destination;

        $this->assertIsArray($destinations);
        $this->assertCount(2, $destinations);
        $this->assertContains('one@example.com', $destinations);
        $this->assertContains('two@example.com', $destinations);
    }

    /** @test */
    public function destination_append_returns_empty_array_when_no_recipients(): void
    {
        $project = Project::factory()->create();
        $email = Email::factory()->create(['project_id' => $project->id]);

        $email->load('recipients');
        $this->assertEquals([], $email->destination);
    }
}
