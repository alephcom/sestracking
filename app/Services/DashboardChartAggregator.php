<?php

namespace App\Services;

use Carbon\Carbon;

class DashboardChartAggregator
{
    public const MINUTES_30 = 30;
    public const MINUTES_HOUR = 60;
    public const MINUTES_DAY = 1440;
    public const MINUTES_WEEK = 10080;

    /**
     * Select bucket size in minutes from the selected range length.
     */
    public function bucketMinutes(Carbon $from, Carbon $to): int
    {
        $seconds = abs($to->diffInSeconds($from));

        if ($seconds <= 86400) {
            return self::MINUTES_30;
        }

        if ($seconds <= 7 * 86400) {
            return self::MINUTES_HOUR;
        }

        if ($seconds <= 90 * 86400) {
            return self::MINUTES_DAY;
        }

        return self::MINUTES_WEEK;
    }

    /**
     * Short granularity label for the API / frontend.
     */
    public function granularity(int $minutes): string
    {
        return match ($minutes) {
            self::MINUTES_30 => '30m',
            self::MINUTES_HOUR => '1h',
            self::MINUTES_DAY => '1d',
            self::MINUTES_WEEK => '1w',
            default => '1d',
        };
    }

    /**
     * Floor a local-time instant to the bucket key string used on the chart axis.
     */
    public function bucketKey(Carbon $localTime, int $minutes): string
    {
        $time = $localTime->copy();

        if ($minutes >= self::MINUTES_WEEK) {
            return $time->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        }

        if ($minutes >= self::MINUTES_DAY) {
            return $time->format('Y-m-d');
        }

        $totalMinutes = ($time->hour * 60) + $time->minute;
        $floored = intdiv($totalMinutes, $minutes) * $minutes;

        return $time->copy()->startOfDay()->addMinutes($floored)->format('Y-m-d H:i:00');
    }

    /**
     * Build a continuous label axis covering [from, to] in the viewer's local offset.
     *
     * @return list<string>
     */
    public function buildLabels(Carbon $from, Carbon $to, int $tzOffset, int $minutes): array
    {
        $localFrom = $from->copy()->addMinutes($tzOffset);
        $localTo = $to->copy()->addMinutes($tzOffset);

        $cursor = Carbon::parse($this->bucketKey($localFrom, $minutes));
        $end = Carbon::parse($this->bucketKey($localTo, $minutes));

        $labels = [];
        $max = 10000;

        while ($cursor->lte($end) && count($labels) < $max) {
            $labels[] = $this->bucketKey($cursor, $minutes);
            $cursor->addMinutes($minutes);
        }

        return $labels;
    }

    /**
     * MySQL SELECT fragment and bindings for adaptive bucket grouping.
     *
     * @return array{0: string, 1: list<int>}
     */
    public function mysqlBucketSelect(int $tzOffset, int $minutes): array
    {
        if ($minutes < self::MINUTES_DAY) {
            return [
                're.type, FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(DATE_ADD(re.event_at, INTERVAL ? MINUTE)) / (? * 60)) * (? * 60)) as bucket, COUNT(*) as count',
                [$tzOffset, $minutes, $minutes],
            ];
        }

        if ($minutes === self::MINUTES_DAY) {
            return [
                're.type, DATE(DATE_ADD(re.event_at, INTERVAL ? MINUTE)) as bucket, COUNT(*) as count',
                [$tzOffset],
            ];
        }

        // Align weeks to Monday (MySQL WEEKDAY: Monday = 0)
        return [
            're.type, DATE(DATE_SUB(DATE(DATE_ADD(re.event_at, INTERVAL ? MINUTE)), INTERVAL WEEKDAY(DATE(DATE_ADD(re.event_at, INTERVAL ? MINUTE))) DAY)) as bucket, COUNT(*) as count',
            [$tzOffset, $tzOffset],
        ];
    }
}
