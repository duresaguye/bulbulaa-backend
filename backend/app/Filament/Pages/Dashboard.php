<?php

namespace App\Filament\Pages;

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Filament\Resources\AnnualReports\AnnualReportResource;
use App\Filament\Resources\MonthlyReports\MonthlyReportResource;
use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use App\Filament\Support\OfficeScope;
use App\Models\AnnualReport;
use App\Models\AnnualTarget;
use App\Models\Department;
use App\Models\MonthlyReport;
use App\Models\ReportReview;
use App\Models\User;
use App\Models\WeeklyProgressReport;
use App\Services\EthiopianCalendar;
use App\Services\OfficeOverview;
use App\Services\ReportingCalendar;
use App\Services\TargetProgress;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * One overview page whose content follows the user's responsibilities:
 * organisation-wide oversight, supervising subordinate offices, running an office, or preparing reports.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Overview';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected string $view = 'filament.pages.dashboard';

    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $unit = null;

    /**
     * @var array<string, mixed>
     */
    private array $memo = [];

    public function mount(): void
    {
        $this->year ??= EthiopianCalendar::today()['year'];
    }

    public function getSubheading(): ?string
    {
        $today = EthiopianCalendar::today();

        return 'Today is '.EthiopianCalendar::monthName($today['month']).' '.$today['day'].', '.$today['year'].' E.C., week '.$today['week'].' of the month.';
    }

    public function user(): User
    {
        return OfficeScope::user();
    }

    /**
     * organization, supervisor, office, officer, viewer, or none.
     */
    public function mode(): string
    {
        return $this->memo['mode'] ??= (function (): string {
            $user = $this->user();

            return match (true) {
                $user->hasOrganizationWideScope() => 'organization',
                $user->reviewableDepartmentIds() !== [] || $user->distributableDepartmentIds() !== [] => 'supervisor',
                $user->hasPermission('reports.submit') => 'office',
                $user->hasPermission('reports.manage') => 'officer',
                $user->hasPermission('reports.view') => 'viewer',
                default => 'none',
            };
        })();
    }

    public function overseesOffices(): bool
    {
        return in_array($this->mode(), ['organization', 'supervisor'], true);
    }

    /**
     * @return array<int, int>
     */
    public function yearOptions(): array
    {
        return AnnualTarget::query()
            ->distinct()
            ->pluck('fiscal_year')
            ->push(EthiopianCalendar::today()['year'])
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn ($year) => [(int) $year => (int) $year])
            ->all();
    }

    /**
     * Offices that can be used to narrow the oversight view to one branch of the organisation.
     *
     * @return array<int, string>
     */
    public function unitOptions(): array
    {
        $scope = $this->scopeDepartmentIds(ignoreUnit: true);

        return Department::query()
            ->whereIn('id', $scope)
            ->whereHas('children')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return list<int>
     */
    public function scopeDepartmentIds(bool $ignoreUnit = false): array
    {
        $ids = $this->user()->visibleDepartmentIds();

        if (! $ignoreUnit && $this->unit && in_array($this->unit, $ids, true)) {
            return [$this->unit, ...Department::descendantIdsOf([$this->unit])];
        }

        return $ids;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function officeRows(): Collection
    {
        return $this->memo['rows'] ??= app(OfficeOverview::class)->rows($this->scopeDepartmentIds(), (int) $this->year);
    }

    /**
     * @return array<string, array{label: string, value: string, detail: string|null}>
     */
    public function metrics(): array
    {
        $rows = $this->officeRows();
        $week = app(ReportingCalendar::class)->reportableWeek();
        $required = $rows->filter(fn (array $row) => $row['week_state'] !== 'not_required');
        $received = $required->filter(fn (array $row) => in_array($row['week_state'], ReportStatus::reportedValues(), true));
        $targets = $rows->flatMap(fn (array $row) => $row['targets']);
        $onTrack = $targets->where('summary.status', 'on_track')->count();
        $judged = $targets->where('summary.status', '!=', 'not_started')->count();

        return [
            'offices' => ['label' => 'Offices', 'value' => (string) $rows->count(), 'detail' => null],
            'weekly' => [
                'label' => 'Weekly reports in',
                'value' => $received->count().' of '.$required->count(),
                'detail' => EthiopianCalendar::monthName($week['month']).' week '.$week['week'],
            ],
            'review' => [
                'label' => 'Awaiting review',
                'value' => (string) $rows->sum('awaiting_review'),
                'detail' => null,
            ],
            'overdue' => [
                'label' => 'Overdue reports',
                'value' => (string) $rows->sum('overdue'),
                'detail' => 'This year',
            ],
            'targets' => [
                'label' => 'Targets on track',
                'value' => $onTrack.' of '.$judged,
                'detail' => $targets->count() > $judged ? ($targets->count() - $judged).' not yet due' : null,
            ],
        ];
    }

    /**
     * Offices with something wrong, most urgent first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function officesNeedingAttention(): Collection
    {
        return $this->officeRows()
            ->filter(fn (array $row) => $row['overdue'] > 0
                || $row['counts']['behind'] > 0
                || $row['counts']['at_risk'] > 0
                || in_array($row['week_state'], ['overdue', 'returned'], true)
                || in_array($row['month_state'], ['overdue', 'returned'], true))
            ->sortByDesc(fn (array $row) => $row['overdue'] * 10 + $row['counts']['behind'] * 5 + $row['counts']['at_risk'])
            ->take(8)
            ->values();
    }

    /**
     * @return Collection<int, array{target: AnnualTarget, summary: array<string, mixed>, office: Department}>
     */
    public function targetsAtRisk(): Collection
    {
        return $this->officeRows()
            ->flatMap(fn (array $row) => $row['targets']->map(fn (array $item) => [...$item, 'office' => $row['department']]))
            ->filter(fn (array $item) => in_array($item['summary']['status'], ['behind', 'at_risk'], true))
            ->sortBy(fn (array $item) => $item['summary']['pace'])
            ->take(6)
            ->values();
    }

    /**
     * @return Collection<int, ReportReview>
     */
    public function recentActivity(): Collection
    {
        return app(OfficeOverview::class)->recentActivity($this->scopeDepartmentIds());
    }

    public function reviewCount(): int
    {
        return $this->memo['reviewCount'] ??= app(OfficeOverview::class)->reviewQueueCount($this->user());
    }

    /**
     * The user's own offices, for office heads and reporting officers.
     *
     * @return Collection<int, Department>
     */
    public function myOffices(): Collection
    {
        return $this->memo['myOffices'] ??= Department::query()
            ->whereIn('id', $this->user()->memberDepartmentIds())
            ->orderBy('name')
            ->get();
    }

    /**
     * Concrete things to do now, each with one button.
     *
     * @return list<array{title: string, detail: string, state: string, url: string|null, action: string|null}>
     */
    public function nextActions(): array
    {
        $calendar = app(ReportingCalendar::class);
        $user = $this->user();
        $actions = [];

        if ($user->hasPermission('reports.manage')) {
            $week = $calendar->reportableWeek();
            $month = $calendar->reportableMonth();

            foreach ($this->myOffices() as $office) {
                $plannedMonths = $calendar->plannedMonths([$office->id], $week['year'])->get($office->id, []);
                $plannedMonthsForMonth = $month['year'] === $week['year']
                    ? $plannedMonths
                    : $calendar->plannedMonths([$office->id], $month['year'])->get($office->id, []);

                $weekly = WeeklyProgressReport::query()
                    ->where('department_id', $office->id)
                    ->where('ethiopian_fiscal_year', $week['year'])
                    ->where('ethiopian_month', $week['month'])
                    ->where('week_number', $week['week'])
                    ->first();

                $actions[] = $this->reportAction(
                    kind: 'weekly',
                    office: $office,
                    period: EthiopianCalendar::monthName($week['month']).' '.$week['year'].', week '.$week['week'],
                    report: $weekly,
                    state: $calendar->weeklyState($weekly, in_array($week['month'], $plannedMonths, true), $week['year'], $week['month'], $week['week']),
                    due: ReportingCalendar::formatDueDate($calendar->weeklyDueDate($week['year'], $week['month'], $week['week'])),
                    createUrl: WeeklyProgressReportResource::getUrl('create', ['department' => $office->id, 'year' => $week['year'], 'month' => $week['month'], 'week' => $week['week']]),
                );

                $monthly = MonthlyReport::query()
                    ->where('department_id', $office->id)
                    ->where('ethiopian_fiscal_year', $month['year'])
                    ->where('ethiopian_month', $month['month'])
                    ->first();

                $actions[] = $this->reportAction(
                    kind: 'monthly',
                    office: $office,
                    period: EthiopianCalendar::monthName($month['month']).' '.$month['year'],
                    report: $monthly,
                    state: $calendar->monthlyState($monthly, in_array($month['month'], $plannedMonthsForMonth, true), $month['year'], $month['month']),
                    due: ReportingCalendar::formatDueDate($calendar->monthlyDueDate($month['year'], $month['month'])),
                    createUrl: MonthlyReportResource::getUrl('create', ['department' => $office->id, 'year' => $month['year'], 'month' => $month['month']]),
                );
            }

            $returned = $this->returnedReports();

            foreach ($returned as $report) {
                $actions[] = [
                    'title' => 'Returned: '.$report['label'],
                    'detail' => $report['comment'],
                    'state' => 'returned',
                    'url' => $report['url'],
                    'action' => 'Correct report',
                ];
            }

            if ($user->hasPermission('reports.submit')) {
                $drafts = $this->draftCount();

                if ($drafts > 0) {
                    $actions[] = [
                        'title' => $drafts === 1 ? 'One draft report is not yet submitted' : "{$drafts} draft reports are not yet submitted",
                        'detail' => 'Drafts prepared by your office stay private until someone with submit permission sends them for review.',
                        'state' => 'draft',
                        'url' => WeeklyProgressReportResource::getUrl('index'),
                        'action' => 'Open drafts',
                    ];
                }
            }
        }

        if ($user->hasPermission('reports.review') && $this->reviewCount() > 0) {
            $count = $this->reviewCount();
            array_unshift($actions, [
                'title' => $count === 1 ? 'One report is waiting for your review' : "{$count} reports are waiting for your review",
                'detail' => 'Approve them or return them to the office with a comment.',
                'state' => 'submitted',
                'url' => ReviewQueue::getUrl(),
                'action' => 'Open review queue',
            ]);
        }

        /** Items that are already done are listed after items that need work. */
        usort($actions, fn (array $a, array $b) => (int) in_array($a['state'], ['submitted', 'approved', 'not_required'], true)
            <=> (int) in_array($b['state'], ['submitted', 'approved', 'not_required'], true));

        return array_values(array_filter($actions, fn (array $action) => $action['state'] !== 'not_required'));
    }

    /**
     * The office's targets with this month's figures and the year so far.
     *
     * @return Collection<int, array{target: AnnualTarget, summary: array<string, mixed>, month_target: float|null, month_actual: float|null}>
     */
    public function myTargets(): Collection
    {
        $progress = app(TargetProgress::class);
        $overview = app(OfficeOverview::class);
        $currentMonth = EthiopianCalendar::today()['month'];
        $isCurrentYear = (int) $this->year === EthiopianCalendar::today()['year'];

        return AnnualTarget::query()
            ->whereIn('department_id', $this->user()->memberDepartmentIds())
            ->where('fiscal_year', $this->year)
            ->where('is_active', true)
            ->with(['department', 'monthlyPlans', 'children.monthlyPlans'])
            ->orderBy('title')
            ->get()
            ->map(function (AnnualTarget $target) use ($progress, $overview, $currentMonth, $isCurrentYear): array {
                $plan = $isCurrentYear ? $target->monthlyPlans->firstWhere('ethiopian_month', $currentMonth) : null;

                return [
                    'target' => $target,
                    'summary' => $progress->summary($target, $overview->asOfMonth($target->fiscal_year)),
                    'month_target' => $plan ? (float) $plan->planned_value : null,
                    'month_actual' => $plan ? $progress->monthActual($plan) : null,
                ];
            });
    }

    /**
     * Latest decisions on the user's own offices' reports.
     *
     * @return Collection<int, ReportReview>
     */
    public function recentFeedback(): Collection
    {
        return ReportReview::query()
            ->whereIn('action', [ReviewAction::Returned->value, ReviewAction::Approved->value])
            ->whereHasMorph('reviewable', [WeeklyProgressReport::class, MonthlyReport::class, AnnualReport::class], fn (Builder $query) => $query->whereIn('department_id', $this->user()->memberDepartmentIds()))
            ->with(['user', 'reviewable.department'])
            ->latest('id')
            ->limit(5)
            ->get();
    }

    public static function reviewableUrl(ReportReview $review): ?string
    {
        $report = $review->reviewable;

        return match (true) {
            $report instanceof WeeklyProgressReport => WeeklyProgressReportResource::getUrl('view', ['record' => $report]),
            $report instanceof MonthlyReport => MonthlyReportResource::getUrl('view', ['record' => $report]),
            $report instanceof AnnualReport => AnnualReportResource::getUrl('view', ['record' => $report]),
            default => null,
        };
    }

    public static function reviewableLabel(ReportReview $review): string
    {
        $report = $review->reviewable;

        if ($report === null) {
            return 'Deleted report';
        }

        $type = match (true) {
            $report instanceof WeeklyProgressReport => 'Weekly report',
            $report instanceof MonthlyReport => 'Monthly report',
            default => 'Annual report',
        };

        return "{$type}, {$report->department->name}, {$report->periodLabel()}";
    }

    /**
     * @return array{title: string, detail: string, state: string, url: string|null, action: string|null}
     */
    private function reportAction(string $kind, Department $office, string $period, WeeklyProgressReport|MonthlyReport|null $report, string $state, string $due, string $createUrl): array
    {
        $resource = $kind === 'weekly' ? WeeklyProgressReportResource::class : MonthlyReportResource::class;
        $name = $kind === 'weekly' ? 'weekly report' : 'monthly report';
        $officeSuffix = $this->myOffices()->count() > 1 ? " for {$office->name}" : '';
        $canWrite = OfficeScope::canWriteFor($office->id);

        return match ($state) {
            'due', 'overdue' => [
                'title' => ucfirst($name)." for {$period}{$officeSuffix}",
                'detail' => ($state === 'overdue' ? 'Overdue. It was due ' : 'Due ').$due.'.',
                'state' => $state,
                'url' => $canWrite ? $createUrl : null,
                'action' => $canWrite ? 'Write '.$name : null,
            ],
            'draft' => [
                'title' => ucfirst($name)." for {$period}{$officeSuffix}",
                'detail' => 'Saved as a draft, not yet submitted. Due '.$due.'.',
                'state' => 'draft',
                'url' => $resource::getUrl($canWrite ? 'edit' : 'view', ['record' => $report]),
                'action' => $canWrite ? 'Continue draft' : 'Open',
            ],
            'returned' => [
                'title' => ucfirst($name)." for {$period}{$officeSuffix}",
                'detail' => 'Returned for correction: '.($report?->latestReturn?->comment ?? ''),
                'state' => 'returned',
                'url' => $resource::getUrl($canWrite ? 'edit' : 'view', ['record' => $report]),
                'action' => $canWrite ? 'Correct report' : 'Open',
            ],
            'not_required' => ['title' => '', 'detail' => '', 'state' => 'not_required', 'url' => null, 'action' => null],
            default => [
                'title' => ucfirst($name)." for {$period}{$officeSuffix}",
                'detail' => ReportStatus::from($state)->getDescription(),
                'state' => $state,
                'url' => $resource::getUrl('view', ['record' => $report]),
                'action' => 'View',
            ],
        };
    }

    /**
     * Returned reports for the user's offices, other than the current week and month which are listed already.
     *
     * @return list<array{label: string, comment: string, url: string}>
     */
    private function returnedReports(): array
    {
        $calendar = app(ReportingCalendar::class);
        $week = $calendar->reportableWeek();
        $month = $calendar->reportableMonth();
        $officeIds = $this->user()->memberDepartmentIds();

        $weekly = WeeklyProgressReport::query()
            ->whereIn('department_id', $officeIds)
            ->where('status', ReportStatus::Returned->value)
            ->whereNot(fn (Builder $query) => $query
                ->where('ethiopian_fiscal_year', $week['year'])
                ->where('ethiopian_month', $week['month'])
                ->where('week_number', $week['week']))
            ->with(['department', 'latestReturn'])
            ->get()
            ->map(fn (WeeklyProgressReport $report) => [
                'label' => 'weekly report, '.$report->department->name.', '.$report->periodLabel(),
                'comment' => (string) $report->latestReturn?->comment,
                'url' => WeeklyProgressReportResource::getUrl('edit', ['record' => $report]),
            ]);

        $monthly = MonthlyReport::query()
            ->whereIn('department_id', $officeIds)
            ->where('status', ReportStatus::Returned->value)
            ->whereNot(fn (Builder $query) => $query
                ->where('ethiopian_fiscal_year', $month['year'])
                ->where('ethiopian_month', $month['month']))
            ->with(['department', 'latestReturn'])
            ->get()
            ->map(fn (MonthlyReport $report) => [
                'label' => 'monthly report, '.$report->department->name.', '.$report->periodLabel(),
                'comment' => (string) $report->latestReturn?->comment,
                'url' => MonthlyReportResource::getUrl('edit', ['record' => $report]),
            ]);

        return $weekly->concat($monthly)->values()->all();
    }

    private function draftCount(): int
    {
        $officeIds = $this->user()->memberDepartmentIds();

        return WeeklyProgressReport::query()->whereIn('department_id', $officeIds)->where('status', ReportStatus::Draft->value)->count()
            + MonthlyReport::query()->whereIn('department_id', $officeIds)->where('status', ReportStatus::Draft->value)->count()
            + AnnualReport::query()->whereIn('department_id', $officeIds)->where('status', ReportStatus::Draft->value)->count();
    }
}
