<?php

namespace App\Services;

use App\Models\MonthlyPlan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Converts between Gregorian dates and the Ethiopian calendar used for reporting periods.
 *
 * Reporting weeks follow the existing convention of "week of month": week 1 is days 1–7,
 * week 4 is days 22–28 and week 5 covers the remaining days of the month.
 */
class EthiopianCalendar
{
    private const ETHIOPIAN_EPOCH_JDN = 1723856;

    private const UNIX_EPOCH_JDN = 2440588;

    /**
     * @return array{year: int, month: int, day: int}
     */
    public static function fromGregorian(CarbonInterface $date): array
    {
        $julianDay = self::UNIX_EPOCH_JDN + intdiv($date->copy()->startOfDay()->getTimestamp() + $date->getOffset(), 86400);
        $remainder = ($julianDay - self::ETHIOPIAN_EPOCH_JDN) % 1461;
        $dayOfYear = ($remainder % 365) + 365 * intdiv($remainder, 1460);

        return [
            'year' => 4 * intdiv($julianDay - self::ETHIOPIAN_EPOCH_JDN, 1461) + intdiv($remainder, 365) - intdiv($remainder, 1460),
            'month' => intdiv($dayOfYear, 30) + 1,
            'day' => ($dayOfYear % 30) + 1,
        ];
    }

    public static function toGregorian(int $year, int $month, int $day): CarbonImmutable
    {
        $julianDay = self::ETHIOPIAN_EPOCH_JDN + 365 + 365 * ($year - 1) + intdiv($year, 4) + 30 * $month + $day - 31;

        return CarbonImmutable::createFromTimestampUTC(($julianDay - self::UNIX_EPOCH_JDN) * 86400)
            ->setTimezone(config('app.timezone'))
            ->startOfDay();
    }

    /**
     * @return array{year: int, month: int, day: int, week: int}
     */
    public static function today(): array
    {
        $today = self::fromGregorian(CarbonImmutable::now());

        return [...$today, 'week' => self::weekOfDay($today['day'])];
    }

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month < 13) {
            return 30;
        }

        return $year % 4 === 3 ? 6 : 5;
    }

    public static function weekOfDay(int $day): int
    {
        return min(5, intdiv($day - 1, 7) + 1);
    }

    public static function weeksInMonth(int $year, int $month): int
    {
        return self::weekOfDay(self::daysInMonth($year, $month));
    }

    /**
     * The last Gregorian day of a reporting week.
     */
    public static function weekEnd(int $year, int $month, int $week): CarbonImmutable
    {
        $lastDay = $week >= 5 ? self::daysInMonth($year, $month) : min($week * 7, self::daysInMonth($year, $month));

        return self::toGregorian($year, $month, $lastDay);
    }

    public static function monthEnd(int $year, int $month): CarbonImmutable
    {
        return self::toGregorian($year, $month, self::daysInMonth($year, $month));
    }

    public static function yearEnd(int $year): CarbonImmutable
    {
        return self::monthEnd($year, 13);
    }

    public static function monthName(int $month): string
    {
        return __(MonthlyPlan::ETHIOPIAN_MONTHS[$month]);
    }

    /**
     * Month numbers to names in the current language, for select inputs and filters.
     *
     * @return array<int, string>
     */
    public static function monthOptions(): array
    {
        return array_map(fn (string $name): string => __($name), MonthlyPlan::ETHIOPIAN_MONTHS);
    }

    /**
     * Quarters group the twelve 30-day months in threes; Pagume belongs to the fourth quarter.
     */
    public static function quarterOfMonth(int $month): int
    {
        return min(4, intdiv($month - 1, 3) + 1);
    }

    /**
     * A date as staff read it in any language: Ethiopian date with month name, then the Gregorian date in numbers.
     * Numbers avoid confusion in Afaan Oromoo, where Ethiopian and Gregorian months share names.
     */
    public static function display(\DateTimeInterface $date, bool $withTime = false): string
    {
        $date = CarbonImmutable::instance($date)->setTimezone(config('app.timezone'));
        $ethiopian = self::fromGregorian($date);

        return __(':date E.C.', ['date' => self::format($ethiopian['year'], $ethiopian['month'], $ethiopian['day'])])
            .' ('.$date->format($withTime ? 'd/m/Y, H:i' : 'd/m/Y').')';
    }

    public static function format(int $year, int $month, ?int $day = null): string
    {
        return trim(self::monthName($month).' '.($day ?? '')).' '.$year;
    }
}
