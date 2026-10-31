<?php

namespace App\Support;

use App\Filament\Pages\OfficePerformance;
use App\Filament\Resources\AnnualReports\AnnualReportResource;
use App\Filament\Resources\AnnualReports\Pages\ViewAnnualReport;
use App\Filament\Resources\MonthlyReports\MonthlyReportResource;
use App\Filament\Resources\MonthlyReports\Pages\ViewMonthlyReport;
use App\Filament\Resources\WeeklyProgressReports\Pages\ViewWeeklyProgressReport;
use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use App\Models\ReportReview;
use App\Services\EthiopianCalendar;
use App\Services\ReportingCalendar;
use App\Services\TargetProgress;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The content of a report or of the office performance overview, prepared once for both the printable page and the
 * Excel file. It reuses the report view pages' own tables, so a download always matches what is on screen.
 */
class ReportDocument
{
    /**
     * @var array<string, array{resource: class-string, page: class-string}>
     */
    public const TYPES = [
        'weekly' => ['resource' => WeeklyProgressReportResource::class, 'page' => ViewWeeklyProgressReport::class],
        'monthly' => ['resource' => MonthlyReportResource::class, 'page' => ViewMonthlyReport::class],
        'annual' => ['resource' => AnnualReportResource::class, 'page' => ViewAnnualReport::class],
    ];

    /**
     * The export type ("weekly", "monthly", "annual") for a report view page.
     */
    public static function typeForPage(string $pageClass): string
    {
        foreach (self::TYPES as $type => $classes) {
            if ($classes['page'] === $pageClass) {
                return $type;
            }
        }

        throw new \InvalidArgumentException("No export type for {$pageClass}.");
    }

    /**
     * Finds a report the signed-in user is allowed to open, using the same scope as the report screens.
     */
    public static function findVisible(string $type, int|string $id): Model
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $resource = self::TYPES[$type]['resource'];
        $record = $resource::getEloquentQuery()->whereKey($id)->firstOrFail();

        abort_unless($resource::canView($record), 403);

        return $record;
    }

    /**
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     filename: string,
     *     ascii_filename: string,
     *     status: string,
     *     meta: list<array{label: string, value: string}>,
     *     results: array{heading: string, target_label: string, actual_label: string, rows: list<array{title: string, unit: string, target: string, actual: string, achievement: float|null, note: string|null}>}|null,
     *     narrative: list<array{label: string, value: string|null}>,
     *     reviews: list<array{action: string, person: string, when: string, comment: string|null}>,
     * }
     */
    public static function forReport(string $type, Model $record): array
    {
        /** The view page only needs its record to build the results table and the narrative. */
        $page = app(self::TYPES[$type]['page']);
        $page->record = $record;

        $resource = self::TYPES[$type]['resource'];
        $subtitle = __(':report for :period', ['report' => ucfirst($resource::getModelLabel()), 'period' => $record->periodLabel()]);

        return [
            'title' => $record->department->name,
            'subtitle' => $subtitle,
            'filename' => self::filename($record->department->name.' '.$record->periodLabel()),
            'ascii_filename' => "{$type}-report-{$record->getKey()}",
            'status' => $record->status->getLabel(),
            'meta' => [
                ['label' => __('Office'), 'value' => $record->department->name],
                ['label' => __('Period'), 'value' => $record->periodLabel()],
                ['label' => __('Status'), 'value' => $record->status->getLabel()],
                ['label' => __('Prepared by'), 'value' => $record->preparedBy?->name ?? '—'],
                ['label' => __('Submitted'), 'value' => $record->submitted_at
                    ? $record->submittedBy?->name.', '.self::date($record->submitted_at)
                    : __('Not yet')],
            ],
            'results' => $page->resultsTable(),
            'narrative' => $page->narrative(),
            'reviews' => $record->reviews()->with('user')->get()
                ->map(fn (ReportReview $review): array => [
                    'action' => $review->action->getLabel(),
                    'person' => $review->user?->name ?? __('Unknown user'),
                    'when' => self::date($review->created_at),
                    'comment' => $review->comment,
                ])->all(),
        ];
    }

    /**
     * The office performance table with the same filters as on screen.
     *
     * @return array{title: string, subtitle: string, filename: string, ascii_filename: string, week_label: string, month_label: string, rows: list<array<string, mixed>>}
     */
    public static function forOfficePerformance(int $year, ?int $unit, string $status): array
    {
        $page = app(OfficePerformance::class);
        $page->year = $year;
        $page->unit = $unit;
        $page->status = array_key_exists($status, $page->statusOptions()) ? $status : 'all';

        $calendar = app(ReportingCalendar::class);
        $week = $calendar->reportableWeek();
        $month = $calendar->reportableMonth();

        return [
            'title' => __('Office performance'),
            'subtitle' => __('Year :year E.C.', ['year' => $year]).' — '.$page->statusOptions()[$page->status],
            'filename' => self::filename(__('Office performance').' '.$year),
            'ascii_filename' => "office-performance-{$year}",
            'week_label' => __('Week :week, :month', ['week' => $week['week'], 'month' => EthiopianCalendar::monthName($week['month'])]),
            'month_label' => __(':month report', ['month' => EthiopianCalendar::monthName($month['month'])]),
            'rows' => $page->rows()->map(fn (array $row): array => [
                'office' => $row['department']->name,
                'depth' => $row['depth'],
                'on_track' => $row['counts']['on_track'],
                'at_risk' => $row['counts']['at_risk'],
                'behind' => $row['counts']['behind'],
                'not_started' => $row['counts']['not_started'],
                'has_targets' => $row['targets']->isNotEmpty(),
                'week' => $row['week_state'] === 'not_required' ? '—' : ReportingCalendar::stateLabel($row['week_state']),
                'month' => $row['month_state'] === 'not_required' ? '—' : ReportingCalendar::stateLabel($row['month_state']),
                'overdue' => $row['overdue'],
                'targets' => $row['targets']->map(fn (array $item): array => [
                    'title' => $item['target']->title,
                    'unit' => $item['target']->measurement_unit,
                    'target' => $item['target']->formatValue($item['summary']['target']),
                    'expected' => $item['target']->formatValue($item['summary']['expected']),
                    'actual' => $item['target']->formatValue($item['summary']['actual']),
                    'achievement' => $item['summary']['achievement'],
                    'pace' => TargetProgress::paceLabel($item['summary']['status']),
                ])->values()->all(),
            ])->all(),
        ];
    }

    /**
     * "Printed on" line in both calendars, since staff and partners read either.
     */
    public static function printedOn(): string
    {
        return __('Printed :date', ['date' => EthiopianCalendar::display(CarbonImmutable::now(), withTime: true)]);
    }

    private static function date(\DateTimeInterface $date): string
    {
        return EthiopianCalendar::display($date);
    }

    /**
     * A file name that is safe on every system but keeps Ethiopic letters readable.
     */
    private static function filename(string $name): string
    {
        $safe = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $name) ?? 'report', '-');

        return mb_strtolower($safe !== '' ? $safe : 'report');
    }
}
