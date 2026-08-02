<?php

namespace Tests\Unit;

use App\Services\DashboardChartAggregator;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardChartAggregatorTest extends TestCase
{
    private DashboardChartAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = new DashboardChartAggregator;
    }

    #[DataProvider('bucketMinutesProvider')]
    public function test_bucket_minutes_ladder(string $from, string $to, int $expected): void
    {
        $this->assertSame(
            $expected,
            $this->aggregator->bucketMinutes(Carbon::parse($from), Carbon::parse($to))
        );
    }

    public static function bucketMinutesProvider(): array
    {
        return [
            'one day' => ['2026-08-01 00:00:00', '2026-08-01 23:59:59', DashboardChartAggregator::MINUTES_30],
            'exactly one day' => ['2026-08-01 00:00:00', '2026-08-02 00:00:00', DashboardChartAggregator::MINUTES_30],
            'two days' => ['2026-08-01 00:00:00', '2026-08-03 00:00:00', DashboardChartAggregator::MINUTES_HOUR],
            'seven days' => ['2026-08-01 00:00:00', '2026-08-08 00:00:00', DashboardChartAggregator::MINUTES_HOUR],
            'eight days' => ['2026-08-01 00:00:00', '2026-08-09 00:00:00', DashboardChartAggregator::MINUTES_DAY],
            'ninety days' => ['2026-01-01 00:00:00', '2026-04-01 00:00:00', DashboardChartAggregator::MINUTES_DAY],
            'over ninety days' => ['2026-01-01 00:00:00', '2026-05-01 00:00:00', DashboardChartAggregator::MINUTES_WEEK],
        ];
    }

    public function test_granularity_labels(): void
    {
        $this->assertSame('30m', $this->aggregator->granularity(DashboardChartAggregator::MINUTES_30));
        $this->assertSame('1h', $this->aggregator->granularity(DashboardChartAggregator::MINUTES_HOUR));
        $this->assertSame('1d', $this->aggregator->granularity(DashboardChartAggregator::MINUTES_DAY));
        $this->assertSame('1w', $this->aggregator->granularity(DashboardChartAggregator::MINUTES_WEEK));
    }

    public function test_bucket_key_floors_to_thirty_minutes(): void
    {
        $this->assertSame(
            '2026-08-02 10:00:00',
            $this->aggregator->bucketKey(Carbon::parse('2026-08-02 10:17:42'), DashboardChartAggregator::MINUTES_30)
        );
        $this->assertSame(
            '2026-08-02 10:30:00',
            $this->aggregator->bucketKey(Carbon::parse('2026-08-02 10:30:00'), DashboardChartAggregator::MINUTES_30)
        );
        $this->assertSame(
            '2026-08-02 10:30:00',
            $this->aggregator->bucketKey(Carbon::parse('2026-08-02 10:59:59'), DashboardChartAggregator::MINUTES_30)
        );
    }

    public function test_bucket_key_floors_to_hour_and_day_and_week(): void
    {
        $this->assertSame(
            '2026-08-02 14:00:00',
            $this->aggregator->bucketKey(Carbon::parse('2026-08-02 14:45:00'), DashboardChartAggregator::MINUTES_HOUR)
        );
        $this->assertSame(
            '2026-08-02',
            $this->aggregator->bucketKey(Carbon::parse('2026-08-02 14:45:00'), DashboardChartAggregator::MINUTES_DAY)
        );
        // Wednesday Aug 5 2026 -> Monday Aug 3
        $this->assertSame(
            '2026-08-03',
            $this->aggregator->bucketKey(Carbon::parse('2026-08-05 12:00:00'), DashboardChartAggregator::MINUTES_WEEK)
        );
    }

    public function test_build_labels_for_one_day_has_forty_eight_thirty_minute_buckets(): void
    {
        $from = Carbon::parse('2026-08-01 00:00:00');
        $to = Carbon::parse('2026-08-01 23:59:59');

        $labels = $this->aggregator->buildLabels($from, $to, 0, DashboardChartAggregator::MINUTES_30);

        $this->assertCount(48, $labels);
        $this->assertSame('2026-08-01 00:00:00', $labels[0]);
        $this->assertSame('2026-08-01 00:30:00', $labels[1]);
        $this->assertSame('2026-08-01 23:30:00', $labels[47]);
    }

    public function test_build_labels_applies_timezone_offset(): void
    {
        // UTC noon..end maps to local evening when offset is +120 minutes
        $from = Carbon::parse('2026-08-01 12:00:00');
        $to = Carbon::parse('2026-08-01 13:59:59');

        $labels = $this->aggregator->buildLabels($from, $to, 120, DashboardChartAggregator::MINUTES_30);

        $this->assertSame('2026-08-01 14:00:00', $labels[0]);
        $this->assertSame('2026-08-01 15:30:00', end($labels));
    }
}
