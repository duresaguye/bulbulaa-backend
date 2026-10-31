<?php

namespace Tests\Unit;

use App\Services\EthiopianCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EthiopianCalendarTest extends TestCase
{
    /**
     * @return array<string, array{string, int, int, int}>
     */
    public static function knownDates(): array
    {
        return [
            'new year 2017' => ['2024-09-11', 2017, 1, 1],
            'new year after a leap year' => ['2023-09-12', 2016, 1, 1],
            'pagume in a non-leap year' => ['2024-09-10', 2016, 13, 5],
            'tikimt 2019' => ['2026-10-24', 2019, 2, 14],
        ];
    }

    #[DataProvider('knownDates')]
    public function test_it_converts_gregorian_dates_both_ways(string $gregorian, int $year, int $month, int $day): void
    {
        $date = CarbonImmutable::parse($gregorian);

        $this->assertSame(['year' => $year, 'month' => $month, 'day' => $day], EthiopianCalendar::fromGregorian($date));
        $this->assertSame($gregorian, EthiopianCalendar::toGregorian($year, $month, $day)->toDateString());
    }

    public function test_pagume_length_and_reporting_weeks(): void
    {
        $this->assertSame(6, EthiopianCalendar::daysInMonth(2015, 13));
        $this->assertSame(5, EthiopianCalendar::daysInMonth(2016, 13));
        $this->assertSame(5, EthiopianCalendar::weeksInMonth(2016, 1));
        $this->assertSame(1, EthiopianCalendar::weeksInMonth(2016, 13));
        $this->assertSame(5, EthiopianCalendar::weekOfDay(30));
        $this->assertSame(4, EthiopianCalendar::quarterOfMonth(13));
    }
}
