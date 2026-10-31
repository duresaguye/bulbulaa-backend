<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\MonthlyPlan;
use App\Models\MonthlyReport;
use App\Models\WeeklyProgressReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out which reports each office owes, when they are due, and whether they are overdue.
 *
 * An office owes weekly and monthly reports only for months in which it has monthly targets.
 */
class ReportingCalendar
{
    /**
     * @var array{year: int, month: int, day: int, week: int}
     */
    public readonly array $today;

    public function __construct()
    {
        $this->today = EthiopianCalendar::today();
    }

    /**
     * The most recent week that has ended, which is the weekly report people should be completing now.
     *
     * @return array{year: int, month: int, week: int}
     */
    public function reportableWeek(): array
    {
        ['year' => $year, 'month' => $month, 'week' => $week] = $this->today;

        if ($week > 1) {
            return ['year' => $year, 'month' => $month, 'week' => $week - 1];
        }

        [$year, $month] = $month === 1 ? [$year - 1, 13] : [$year, $month - 1];

        return ['year' => $year, 'month' => $month, 'week' => EthiopianCalendar::weeksInMonth($year, $month)];
    }

    /**
     * The most recent month that has ended.
     *
     * @return array{year: int, month: int}
     */
    public function reportableMonth(): array
    {
        ['year' => $year, 'month' => $month] = $this->today;

        return $month === 1 ? ['year' => $year - 1, 'month' => 13] : ['year' => $year, 'month' => $month - 1];
    }

    public function weeklyDueDate(int $year, int $month, int $week): CarbonImmutable
    {
        return EthiopianCalendar::weekEnd($year, $month, $week)->addDays((int) config('reporting.weekly_grace_days'));
    }

    public function monthlyDueDate(int $year, int $month): CarbonImmutable
    {
        return EthiopianCalendar::monthEnd($year, $month)->addDays((int) config('reporting.monthly_grace_days'));
    }

    public function annualDueDate(int $year): CarbonImmutable
    {
        return EthiopianCalendar::yearEnd($year)->addDays((int) config('reporting.annual_grace_days'));
    }

    public static function formatDueDate(CarbonImmutable $date): string
    {
        $ethiopian = EthiopianCalendar::fromGregorian($date);

        return EthiopianCalendar::monthName($ethiopian['month']).' '.$ethiopian['day'].' ('.$date->format('j M Y').')';
    }

    /**
     * Months of the year, keyed by office, in which each office has active monthly targets.
     *
     * @param  list<int>  $departmentIds
     * @return Collection<int, list<int>>
     */
    public function plannedMonths(array $departmentIds, int $year): Collection
    {
        return MonthlyPlan::query()
            ->join('annual_targets', 'annual_targets.id', '=', 'monthly_plans.annual_target_id')
            ->whereIn('annual_targets.department_id', $departmentIds)
            ->where('annual_targets.fiscal_year', $year)
            ->where('annual_targets.is_active', true)
            ->distinct()
            ->get(['annual_targets.department_id', 'monthly_plans.ethiopian_month'])
            ->groupBy('department_id')
            ->map(fn (Collection $rows) => $rows->pluck('ethiopian_month')->map(fn ($month) => (int) $month)->unique()->sort()->values()->all());
    }

    /**
     * Status of one weekly obligation: a report status value, or overdue, due, or not_required.
     */
    public function weeklyState(?WeeklyProgressReport $report, bool $isRequired, int $year, int $month, int $week): string
    {
        if ($report !== null && $report->status !== ReportStatus::Draft) {
            return $report->status->value;
        }

        if (! $isRequired) {
            return 'not_required';
        }

        if (now()->greaterThan($this->weeklyDueDate($year, $month, $week)->endOfDay())) {
            return 'overdue';
        }

        return $report !== null ? 'draft' : 'due';
    }

    public function monthlyState(?MonthlyReport $report, bool $isRequired, int $year, int $month): string
    {
        if ($report !== null && $report->status !== ReportStatus::Draft) {
            return $report->status->value;
        }

        if (! $isRequired) {
            return 'not_required';
        }

        if (now()->greaterThan($this->monthlyDueDate($year, $month)->endOfDay())) {
            return 'overdue';
        }

        return $report !== null ? 'draft' : 'due';
    }

    /**
     * Count of weekly and monthly reports, across the year so far, that are past their deadline and not submitted.
     *
     * @param  list<int>  $departmentIds
     * @return array<int, int> overdue count keyed by office
     */
    public function overdueCounts(array $departmentIds, int $year): array
    {
        $plannedMonths = $this->plannedMonths($departmentIds, $year);
        $reportedWeeks = WeeklyProgressReport::query()
            ->whereIn('department_id', $departmentIds)
            ->where('ethiopian_fiscal_year', $year)
            ->whereIn('status', [...ReportStatus::reportedValues(), ReportStatus::Returned->value])
            ->get(['department_id', 'ethiopian_month', 'week_number'])
            ->map(fn ($report) => "{$report->department_id}:{$report->ethiopian_month}:{$report->week_number}")
            ->flip();
        $reportedMonths = MonthlyReport::query()
            ->whereIn('department_id', $departmentIds)
            ->where('ethiopian_fiscal_year', $year)
            ->whereIn('status', [...ReportStatus::reportedValues(), ReportStatus::Returned->value])
            ->get(['department_id', 'ethiopian_month'])
            ->map(fn ($report) => "{$report->department_id}:{$report->ethiopian_month}")
            ->flip();

        $counts = [];

        foreach ($departmentIds as $departmentId) {
            $count = 0;

            foreach ($plannedMonths->get($departmentId, []) as $month) {
                for ($week = 1; $week <= EthiopianCalendar::weeksInMonth($year, $month); $week++) {
                    if (now()->greaterThan($this->weeklyDueDate($year, $month, $week)->endOfDay())
                        && ! $reportedWeeks->has("{$departmentId}:{$month}:{$week}")) {
                        $count++;
                    }
                }

                if (now()->greaterThan($this->monthlyDueDate($year, $month)->endOfDay())
                    && ! $reportedMonths->has("{$departmentId}:{$month}")) {
                    $count++;
                }
            }

            $counts[$departmentId] = $count;
        }

        return $counts;
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'overdue' => 'Overdue',
            'due' => 'Not started',
            'not_required' => 'No targets',
            default => ReportStatus::from($state)->getLabel(),
        };
    }

    public static function stateColor(string $state): string
    {
        return match ($state) {
            'overdue' => 'danger',
            'due', 'not_required' => 'gray',
            default => ReportStatus::from($state)->getColor(),
        };
    }
}
