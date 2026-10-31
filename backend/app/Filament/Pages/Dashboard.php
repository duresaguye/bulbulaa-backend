<?php

namespace App\Filament\Pages;

use App\Enums\ReportStatus;
use App\Enums\ReviewAction;
use App\Filament\Resources\AnnualReports\AnnualReportResource;
use App\Filament\Resources\MonthlyReports\MonthlyReportResource;
use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use App\Filament\Support\Concerns\TranslatesPageLabels;
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
    use TranslatesPageLabels;

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

        return __('Today is :month :day, :year E.C., week :week of the month.', [
            'month' => EthiopianCalendar::monthName($today['month']),
            'day' => $today['day'],
            'year' => $today['year'],
            'week' => $today['week'],
        ]);
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
            'offices' => ['label' => __('Offices'), 'value' => (string) $rows->count(), 'detail' => null],
            'weekly' => [
                'label' => __('Weekly reports in'),
                'value' => __(':actual of :target', ['actual' => $received->count(), 'target' => $required->count()]),
                'detail' => __(':month week :week', ['month' => EthiopianCalendar::monthName($week['month']), 'week' => $week['week']]),
            ],
            'review' => [
                'label' => __('Awaiting review'),
                'value' => (string) $rows->sum('awaiting_review'),
                'detail' => null,
            ],
            'overdue' => [
                'label' => __('Overdue reports'),
                'value' => (string) $rows->sum('overdue'),
                'detail' => __('This year'),
            ],
            'targets' => [
                'label' => __('Targets on track'),
                'value' => __(':actual of :target', ['actual' => $onTrack, 'target' => $judged]),
                'detail' => $targets->count() > $judged ? __(':count not yet due', ['count' => $targets->count() - $judged]) : null,
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
                    period: __(':month :year, week :week', ['month' => EthiopianCalendar::monthName($week['month']), 'year' => $week['year'], 'week' => $week['week']]),
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
                    'title' => __('Returned: :report', ['report' => $report['label']]),
                    'detail' => $report['comment'],
                    'state' => 'returned',
                    'url' => $report['url'],
                    'action' => __('Correct report'),
                ];
            }

            if ($user->hasPermission('reports.submit')) {
                $drafts = $this->draftCount();

                if ($drafts > 0) {
                    $actions[] = [
                        'title' => $drafts === 1 ? __('One draft report is not yet submitted') : __(':count draft reports are not yet submitted', ['count' => $drafts]),
                        'detail' => __('Drafts prepared by your office stay private until someone with submit permission sends them for review.'),
                        'state' => 'draft',
                        'url' => WeeklyProgressReportResource::getUrl('index'),
                        'action' => __('Open drafts'),
                    ];
                }
            }
        }

        if ($user->hasPermission('reports.review') && $this->reviewCount() > 0) {
            $count = $this->reviewCount();
            array_unshift($actions, [
                'title' => $count === 1 ? __('One report is waiting for your review') : __(':count reports are waiting for your review', ['count' => $count]),
                'detail' => __('Approve them or return them to the office with a comment.'),
                'state' => 'submitted',
                'url' => ReviewQueue::getUrl(),
                'action' => __('Open review queue'),
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
            return __('Deleted report');
        }

        $replace = ['office' => $report->department->name, 'period' => $report->periodLabel()];

        return match (true) {
            $report instanceof WeeklyProgressReport => __('Weekly report, :office, :period', $replace),
            $report instanceof MonthlyReport => __('Monthly report, :office, :period', $replace),
            default => __('Annual report, :office, :period', $replace),
        };
    }

    /**
     * @return array{title: string, detail: string, state: string, url: string|null, action: string|null}
     */
    private function reportAction(string $kind, Department $office, string $period, WeeklyProgressReport|MonthlyReport|null $report, string $state, string $due, string $createUrl): array
    {
        $resource = $kind === 'weekly' ? WeeklyProgressReportResource::class : MonthlyReportResource::class;
        $title = $kind === 'weekly'
            ? __('Weekly report for :period', ['period' => $period])
            : __('Monthly report for :period', ['period' => $period]);
        $title = $this->myOffices()->count() > 1 ? __(':report, :office', ['report' => $title, 'office' => $office->name]) : $title;
        $canWrite = OfficeScope::canWriteFor($office->id);

        return match ($state) {
            'due', 'overdue' => [
                'title' => $title,
                'detail' => $state === 'overdue' ? __('Overdue. It was due :date.', ['date' => $due]) : __('Due :date.', ['date' => $due]),
                'state' => $state,
                'url' => $canWrite ? $createUrl : null,
                'action' => $canWrite ? ($kind === 'weekly' ? __('Write weekly report') : __('Write monthly report')) : null,
            ],
            'draft' => [
                'title' => $title,
                'detail' => __('Saved as a draft, not yet submitted. Due :date.', ['date' => $due]),
                'state' => 'draft',
                'url' => $resource::getUrl($canWrite ? 'edit' : 'view', ['record' => $report]),
                'action' => $canWrite ? __('Continue draft') : __('Open'),
            ],
            'returned' => [
                'title' => $title,
                'detail' => __('Returned for correction: :comment', ['comment' => $report?->latestReturn?->comment ?? '']),
                'state' => 'returned',
                'url' => $resource::getUrl($canWrite ? 'edit' : 'view', ['record' => $report]),
                'action' => $canWrite ? __('Correct report') : __('Open'),
            ],
            'not_required' => ['title' => '', 'detail' => '', 'state' => 'not_required', 'url' => null, 'action' => null],
            default => [
                'title' => $title,
                'detail' => ReportStatus::from($state)->getDescription(),
                'state' => $state,
                'url' => $resource::getUrl('view', ['record' => $report]),
                'action' => __('View'),
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
                'label' => __('Weekly report, :office, :period', ['office' => $report->department->name, 'period' => $report->periodLabel()]),
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
                'label' => __('Monthly report, :office, :period', ['office' => $report->department->name, 'period' => $report->periodLabel()]),
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
