<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityExportTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();
        $user = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => false]);
        $this->project->users()->attach($user->id, ['role' => 'user']);
        $this->actingAs($user);
    }

    private function email(string $subject, string $sentAt, array $recipients, ?string $eventType = null): Email
    {
        $email = Email::factory()->create([
            'project_id' => $this->project->id,
            'subject' => $subject,
            'sent_at' => $sentAt,
            'opens' => 2,
            'clicks' => 1,
        ]);

        foreach ($recipients as $address => $status) {
            $recipient = EmailRecipient::factory()->create([
                'email_id' => $email->id,
                'address' => $address,
                'status' => $status,
            ]);

            if ($eventType) {
                RecipientEvent::factory()->create(['recipient_id' => $recipient->id, 'type' => $eventType]);
            }
        }

        return $email;
    }

    /** Download the CSV export and return its rows (header first). */
    private function exportCsv(array $params = []): array
    {
        $response = $this->get('/activity/export?'.http_build_query($params + ['format' => 'csv']));
        $response->assertOk();

        // Spout prefixes CSVs with a UTF-8 byte-order mark so Excel detects the encoding.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());
        $lines = array_filter(explode("\n", trim($content)));

        return array_map('str_getcsv', array_values($lines));
    }

    /** @test */
    public function export_includes_status_and_destination_from_recipients()
    {
        $this->email('Welcome aboard', '2026-10-01 10:00:00', [
            'a@example.com' => 'delivered',
            'b@example.com' => 'bounced',
        ]);

        $rows = $this->exportCsv();

        $this->assertSame(['Status', 'Subject', 'Destination', 'Date UTC', 'Opens', 'Clicks'], $rows[0]);
        $this->assertSame(['bounced', 'Welcome aboard', 'a@example.com, b@example.com', '10/01/2026 10:00', '2', '1'], $rows[1]);
    }

    /** @test */
    public function export_filters_by_event_type()
    {
        $this->email('Opened one', '2026-10-01 10:00:00', ['a@example.com' => 'delivered'], 'open');
        $this->email('Bounced one', '2026-10-01 11:00:00', ['b@example.com' => 'bounced'], 'bounce');

        $rows = $this->exportCsv(['eventType' => 'open']);

        $this->assertCount(2, $rows);
        $this->assertSame('Opened one', $rows[1][1]);
    }

    /** @test */
    public function export_search_matches_recipient_address_and_subject()
    {
        $this->email('Invoice', '2026-10-01 10:00:00', ['finance@example.com' => 'delivered']);
        $this->email('Newsletter', '2026-10-01 11:00:00', ['reader@example.com' => 'delivered']);

        $this->assertSame('Invoice', $this->exportCsv(['search' => 'finance'])[1][1]);
        $this->assertSame('Newsletter', $this->exportCsv(['search' => 'Newsl'])[1][1]);
        $this->assertCount(2, $this->exportCsv(['search' => 'finance']));
    }

    /** @test */
    public function export_date_range_uses_sent_at()
    {
        $this->email('Inside', '2026-10-15 12:00:00', ['a@example.com' => 'delivered']);
        $this->email('Outside', '2026-09-01 12:00:00', ['b@example.com' => 'delivered']);

        $rows = $this->exportCsv([
            'dateFrom' => '2026-10-01T00:00:00.000Z',
            'dateTo' => '2026-10-31T23:59:59.999Z',
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('Inside', $rows[1][1]);
    }

    /** @test */
    public function export_matches_on_screen_list()
    {
        $this->email('Opened one', '2026-10-01 10:00:00', ['a@example.com' => 'delivered'], 'open');
        $this->email('Opened two', '2026-10-02 10:00:00', ['c@example.com' => 'complained'], 'open');
        $this->email('Bounced one', '2026-10-03 10:00:00', ['b@example.com' => 'bounced'], 'bounce');

        $params = ['eventType' => 'open'];
        $list = $this->getJson('/activity/list/api?'.http_build_query($params))->json('rows');
        $export = array_slice($this->exportCsv($params), 1);

        $this->assertSame(
            array_map(fn ($row) => [$row['status'], $row['subject']], $list),
            array_map(fn ($row) => [$row[0], $row[1]], $export)
        );
    }

    /** @test */
    public function export_excludes_other_projects()
    {
        Email::factory()->create(['subject' => 'Someone else', 'project_id' => Project::factory()->create()->id]);
        $this->email('Mine', '2026-10-01 10:00:00', ['a@example.com' => 'delivered']);

        $rows = $this->exportCsv();

        $this->assertCount(2, $rows);
        $this->assertSame('Mine', $rows[1][1]);
    }

    /** @test */
    public function excel_export_downloads()
    {
        $this->email('Welcome aboard', '2026-10-01 10:00:00', ['a@example.com' => 'delivered']);

        $response = $this->get('/activity/export?format=excel');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    /** @test */
    public function invalid_date_is_rejected_instead_of_erroring()
    {
        $this->getJson('/activity/list/api?dateFrom=not-a-date&dateTo=also-bad')
            ->assertStatus(422);
    }
}
