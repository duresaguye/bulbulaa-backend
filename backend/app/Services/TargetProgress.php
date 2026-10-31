<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\AnnualTarget;
use App\Models\MonthlyPlan;
use App\Models\MonthlyReportLine;
use App\Models\WeeklyProgressEntry;
use Illuminate\Support\Collection;

/**
 * Calculates actual results against targets using each indicator's own aggregation rule.
 *
 * Source of a month's actual value, in order of preference:
 *  1. The office's monthly report for that month.
 *  2. Otherwise the combined weekly report entries for that month.
 *
 * Drafts and returned reports never count. "Approved only" calculations ignore weekly figures and
 * monthly reports that are still awaiting review, so annual summaries rest on approved data alone.
 */
class TargetProgress
{
    /**
     * @var array<string, array<int, float|null>>
     */
    private array $monthlyCache = [];

    /**
     * Monthly actual values for every allocated month of a target, keyed by Ethiopian month.
     *
     * @return array<int, float|null>
     */
    public function monthlyActuals(AnnualTarget $target, bool $approvedOnly = false): array
    {
        $cacheKey = $target->id.($approvedOnly ? ':approved' : ':reported');

        if (isset($this->monthlyCache[$cacheKey])) {
            return $this->monthlyCache[$cacheKey];
        }

        $plans = $target->monthlyPlans;
        $planIds = $plans->pluck('id');
        $statuses = $approvedOnly ? [ReportStatus::Approved->value] : ReportStatus::reportedValues();

        $monthlyLines = MonthlyReportLine::query()
            ->whereIn('monthly_plan_id', $planIds)
            ->whereHas('report', fn ($query) => $query->whereIn('status', $statuses))
            ->get()
            ->keyBy('monthly_plan_id');

        $weeklyEntries = $approvedOnly
            ? collect()
            : WeeklyProgressEntry::query()
                ->whereIn('monthly_plan_id', $planIds)
                ->whereHas('report', fn ($query) => $query->whereIn('status', ReportStatus::reportedValues()))
                ->with('report:id,week_number')
                ->get()
                ->groupBy('monthly_plan_id');

        $actuals = [];

        foreach ($plans as $plan) {
            $actuals[$plan->ethiopian_month] = $monthlyLines->has($plan->id)
                ? (float) $monthlyLines[$plan->id]->actual_value
                : $this->combineWeekly($target, $weeklyEntries->get($plan->id, collect()));
        }

        return $this->monthlyCache[$cacheKey] = $actuals;
    }

    public function monthActual(MonthlyPlan $plan, bool $approvedOnly = false): ?float
    {
        return $this->monthlyActuals($plan->annualTarget, $approvedOnly)[$plan->ethiopian_month] ?? null;
    }

    /**
     * Combined weekly figures for one monthly target, before any monthly report exists.
     */
    public function weeklyTotalForPlan(MonthlyPlan $plan): ?float
    {
        $entries = WeeklyProgressEntry::query()
            ->where('monthly_plan_id', $plan->id)
            ->whereHas('report', fn ($query) => $query->whereIn('status', ReportStatus::reportedValues()))
            ->with('report:id,week_number')
            ->get();

        return $this->combineWeekly($plan->annualTarget, $entries);
    }

    /**
     * Actual result for the year so far. Targets distributed to subordinate offices roll up their results
     * when the indicator is additive; non-additive results are only meaningful office by office.
     */
    public function annualActual(AnnualTarget $target, bool $approvedOnly = false): ?float
    {
        $values = array_values(array_filter(
            $this->monthlyActuals($target, $approvedOnly),
            fn (?float $value) => $value !== null,
        ));
        $own = $target->aggregation->combine($values);

        if ($target->children->isEmpty() || ! $target->aggregation->isAdditive()) {
            return $own;
        }

        $childValues = $target->children
            ->map(fn (AnnualTarget $child) => $this->annualActual($child, $approvedOnly))
            ->filter(fn (?float $value) => $value !== null);

        if ($own === null && $childValues->isEmpty()) {
            return null;
        }

        return ($own ?? 0) + $childValues->sum();
    }

    /**
     * What the office should have reached by the end of the given month according to its allocation.
     */
    public function expectedToDate(AnnualTarget $target, int $throughMonth): ?float
    {
        $plans = $target->monthlyPlans->filter(fn (MonthlyPlan $plan) => $plan->ethiopian_month <= $throughMonth);

        if ($plans->isEmpty()) {
            return null;
        }

        return $target->aggregation->isAdditive()
            ? (float) $plans->sum('planned_value')
            : (float) $plans->sortBy('ethiopian_month')->last()->planned_value;
    }

    public static function achievement(?float $actual, float|string|null $target): ?float
    {
        if ($actual === null || $target === null || (float) $target <= 0) {
            return null;
        }

        return round($actual / (float) $target * 100, 1);
    }

    /**
     * A full progress summary for one target as of the given month.
     *
     * @return array{target: float, actual: float|null, remaining: float|null, achievement: float|null, expected: float|null, pace: float|null, status: string}
     */
    public function summary(AnnualTarget $target, int $asOfMonth, bool $approvedOnly = false): array
    {
        $targetValue = (float) $target->target_value;
        $actual = $this->annualActual($target, $approvedOnly);
        $expected = $this->expectedToDate($target, $asOfMonth);
        $pace = self::achievement($actual ?? 0.0, $expected);

        return [
            'target' => $targetValue,
            'actual' => $actual,
            'remaining' => $actual === null || ! $target->aggregation->isAdditive() ? null : max(0, $targetValue - $actual),
            'achievement' => self::achievement($actual, $targetValue),
            'expected' => $expected,
            'pace' => $pace,
            'status' => $this->paceStatus($expected, $pace),
        ];
    }

    /**
     * on_track, at_risk, behind, or not_started when nothing was expected yet.
     */
    public function paceStatus(?float $expected, ?float $pace): string
    {
        if ($expected === null || $expected <= 0 || $pace === null) {
            return 'not_started';
        }

        $threshold = (float) config('reporting.at_risk_threshold', 80);

        return match (true) {
            $pace >= 100 => 'on_track',
            $pace >= $threshold => 'at_risk',
            default => 'behind',
        };
    }

    public static function paceLabel(string $status): string
    {
        return match ($status) {
            'on_track' => 'On track',
            'at_risk' => 'At risk',
            'behind' => 'Behind plan',
            default => 'Not started',
        };
    }

    public static function paceColor(string $status): string
    {
        return match ($status) {
            'on_track' => 'success',
            'at_risk' => 'warning',
            'behind' => 'danger',
            default => 'gray',
        };
    }

    /**
     * @param  Collection<int, WeeklyProgressEntry>  $entries
     */
    private function combineWeekly(AnnualTarget $target, Collection $entries): ?float
    {
        $values = $entries
            ->sortBy(fn (WeeklyProgressEntry $entry) => $entry->report?->week_number)
            ->map(fn (WeeklyProgressEntry $entry) => (float) $entry->actual_value)
            ->values()
            ->all();

        return $target->aggregation->combine($values);
    }
}
