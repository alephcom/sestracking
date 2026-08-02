<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailRecipient;
use App\Models\Project;
use App\Models\RecipientEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardChartDensityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function one_day_range_returns_thirty_minute_buckets_with_distinct_counts(): void
    {
        Cache::flush();

        $user = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);
        $project = Project::factory()->create();

        $email = Email::factory()->create([
            'project_id' => $project->id,
            'sent_at' => '2026-08-01 10:00:00',
        ]);
        $recipient = EmailRecipient::factory()->create(['email_id' => $email->id]);

        RecipientEvent::factory()->create([
            'recipient_id' => $recipient->id,
            'type' => 'delivery',
            'event_at' => '2026-08-01 10:15:00',
        ]);
        RecipientEvent::factory()->create([
            'recipient_id' => $recipient->id,
            'type' => 'delivery',
            'event_at' => '2026-08-01 14:45:00',
        ]);
        RecipientEvent::factory()->create([
            'recipient_id' => $recipient->id,
            'type' => 'open',
            'event_at' => '2026-08-01 14:50:00',
        ]);

        $this->actingAs($user);

        $response = $this->getJson(route('dashboard.api', [
            'projectId' => $project->id,
            'dateFrom' => Carbon::parse('2026-08-01 00:00:00')->toIso8601String(),
            'dateTo' => Carbon::parse('2026-08-01 23:59:59')->toIso8601String(),
            'tzOffset' => 0,
        ]));

        $response->assertOk();
        $chart = $response->json('chartData');

        $this->assertSame('30m', $chart['granularity']);
        $this->assertCount(48, $chart['labels']);
        $this->assertSame('2026-08-01 00:00:00', $chart['labels'][0]);
        $this->assertSame('2026-08-01 23:30:00', $chart['labels'][47]);

        $datasetsByLabel = collect($chart['datasets'])->keyBy('label');
        $this->assertTrue($datasetsByLabel->has('Delivery'));
        $this->assertTrue($datasetsByLabel->has('Open'));

        $delivery = $datasetsByLabel['Delivery']['data'];
        $open = $datasetsByLabel['Open']['data'];

        $index1015 = array_search('2026-08-01 10:00:00', $chart['labels'], true);
        $index1445 = array_search('2026-08-01 14:30:00', $chart['labels'], true);

        $this->assertNotFalse($index1015);
        $this->assertNotFalse($index1445);
        $this->assertSame(1, $delivery[$index1015]);
        $this->assertSame(1, $delivery[$index1445]);
        $this->assertSame(1, $open[$index1445]);
        $this->assertSame(0, $delivery[0]);
    }

    /** @test */
    public function multi_week_range_uses_daily_granularity(): void
    {
        Cache::flush();

        $user = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]);
        $project = Project::factory()->create();

        $this->actingAs($user);

        $response = $this->getJson(route('dashboard.api', [
            'projectId' => $project->id,
            'dateFrom' => Carbon::parse('2026-08-01 00:00:00')->toIso8601String(),
            'dateTo' => Carbon::parse('2026-08-20 23:59:59')->toIso8601String(),
            'tzOffset' => 0,
        ]));

        $response->assertOk();
        $this->assertSame('1d', $response->json('chartData.granularity'));
        $this->assertGreaterThan(1, count($response->json('chartData.labels')));
    }
}
