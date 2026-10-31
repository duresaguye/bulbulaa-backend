<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\AnnualReport;
use App\Models\AnnualTarget;
use App\Models\Department;
use App\Models\MonthlyReport;
use App\Models\ReportReview;
use App\Models\User;
use App\Models\WeeklyProgressReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the per-office picture shown on dashboards and the office performance page:
 * target pace, the reports currently due, overdue reports, and reports awaiting review.
 */
class OfficeOverview
{
    public function __construct(
        private TargetProgress $progress,
        private ReportingCalendar $calendar,
    ) {}

    /**
     * The month that pace is measured against for a given year.
     */
    public function asOfMonth(int $year): int
    {
        $reportable = $this->calendar->reportableMonth();

        return match (true) {
            $year < $reportable['year'] => 13,
            $year > $reportable['year'] => 0,
            default => $reportable['month'],
        };
    }

    /**
     * @param  list<int>  $departmentIds
     * @return Collection<int, array{department: Department, depth: int, targets: Collection<int, array{target: AnnualTarget, summary: array<string, mixed>}>, counts: array<string, int>, week_state: string, week_report: WeeklyProgressReport|null, month_state: string, month_report: MonthlyReport|null, overdue: int, awaiting_review: int}>
     */
    public function rows(array $departmentIds, int $year): Collection
    {
        $asOfMonth = $this->asOfMonth($year);
        $week = $this->calendar->reportableWeek();
        $month = $this->calendar->reportableMonth();

        $targets = AnnualTarget::query()
            ->whereIn('department_id', $departmentIds)
            ->where('fiscal_year', $year)
            ->where('is_active', true)
            ->with(['monthlyPlans', 'children.monthlyPlans'])
            ->orderBy('title')
            ->get()
            ->groupBy('department_id');

        $weeklyReports = WeeklyProgressReport::query()
            ->whereIn('department_id', $departmentIds)
            ->where('ethiopian_fiscal_year', $week['year'])
            ->where('ethiopian_month', $week['month'])
            ->where('week_number', $week['week'])
            ->get()
            ->keyBy('department_id');

        $monthlyReports = MonthlyReport::query()
            ->whereIn('department_id', $departmentIds)
            ->where('ethiopian_fiscal_year', $month['year'])
            ->where('ethiopian_month', $month['month'])
            ->get()
            ->keyBy('department_id');

        $weekPlans = $this->calendar->plannedMonths($departmentIds, $week['year']);
        $monthPlans = $week['year'] === $month['year'] ? $weekPlans : $this->calendar->plannedMonths($departmentIds, $month['year']);
        $overdue = $this->calendar->overdueCounts($departmentIds, $this->calendar->today['year']);
        $awaiting = $this->awaitingReviewCounts($departmentIds);

        return Department::tree($departmentIds)->map(function (array $node) use ($targets, $asOfMonth, $weeklyReports, $monthlyReports, $week, $month, $weekPlans, $monthPlans, $overdue, $awaiting): array {
            $department = $node['department'];
            $officeTargets = $targets->get($department->id, collect())->map(fn (AnnualTarget $target): array => [
                'target' => $target,
                'summary' => $this->progress->summary($target, $asOfMonth),
            ]);
            $weekReport = $weeklyReports->get($department->id);
            $monthReport = $monthlyReports->get($department->id);

            return [
                ...$node,
                'targets' => $officeTargets,
                'counts' => [
                    'on_track' => $officeTargets->where('summary.status', 'on_track')->count(),
                    'at_risk' => $officeTargets->where('summary.status', 'at_risk')->count(),
                    'behind' => $officeTargets->where('summary.status', 'behind')->count(),
                    'not_started' => $officeTargets->where('summary.status', 'not_started')->count(),
                ],
                'week_report' => $weekReport,
                'week_state' => $this->calendar->weeklyState(
                    $weekReport,
                    in_array($week['month'], $weekPlans->get($department->id, []), true),
                    $week['year'], $week['month'], $week['week'],
                ),
                'month_report' => $monthReport,
                'month_state' => $this->calendar->monthlyState(
                    $monthReport,
                    in_array($month['month'], $monthPlans->get($department->id, []), true),
                    $month['year'], $month['month'],
                ),
                'overdue' => $overdue[$department->id] ?? 0,
                'awaiting_review' => $awaiting[$department->id] ?? 0,
            ];
        });
    }

    /**
     * Submitted reports, per office, across all three report types.
     *
     * @param  list<int>  $departmentIds
     * @return array<int, int>
     */
    public function awaitingReviewCounts(array $departmentIds): array
    {
        $counts = [];

        foreach ([WeeklyProgressReport::class, MonthlyReport::class, AnnualReport::class] as $model) {
            $model::query()
                ->whereIn('department_id', $departmentIds)
                ->where('status', ReportStatus::Submitted->value)
                ->selectRaw('department_id, count(*) as aggregate')
                ->groupBy('department_id')
                ->pluck('aggregate', 'department_id')
                ->each(function ($count, $departmentId) use (&$counts): void {
                    $counts[$departmentId] = ($counts[$departmentId] ?? 0) + (int) $count;
                });
        }

        return $counts;
    }

    /**
     * Reports the user can act on as a reviewer right now.
     */
    public function reviewQueueCount(User $user): int
    {
        $departmentIds = $user->reviewableDepartmentIds();

        if ($departmentIds === []) {
            return 0;
        }

        $count = 0;

        foreach ([WeeklyProgressReport::class, MonthlyReport::class, AnnualReport::class] as $model) {
            $count += self::reviewQueueQuery($model, $user)->count();
        }

        return $count;
    }

    /**
     * Submitted reports of one type that the user may review, excluding their own.
     *
     * @param  class-string<WeeklyProgressReport|MonthlyReport|AnnualReport>  $model
     */
    public static function reviewQueueQuery(string $model, User $user): Builder
    {
        return $model::query()
            ->whereIn('department_id', $user->reviewableDepartmentIds())
            ->where('status', ReportStatus::Submitted->value)
            ->where(fn (Builder $query) => $query->whereNull('prepared_by')->orWhere('prepared_by', '!=', $user->id))
            ->where(fn (Builder $query) => $query->whereNull('submitted_by')->orWhere('submitted_by', '!=', $user->id));
    }

    /**
     * Latest submissions and decisions for reports from the given offices.
     *
     * @param  list<int>  $departmentIds
     * @return Collection<int, ReportReview>
     */
    public function recentActivity(array $departmentIds, int $limit = 8): Collection
    {
        return ReportReview::query()
            ->whereHasMorph('reviewable', [WeeklyProgressReport::class, MonthlyReport::class, AnnualReport::class], fn (Builder $query) => $query->whereIn('department_id', $departmentIds))
            ->with(['user', 'reviewable.department'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
