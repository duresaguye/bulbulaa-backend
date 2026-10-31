<?php

namespace App\Filament\Resources\MonthlyReports\Schemas;

use App\Enums\ReportStatus;
use App\Filament\Support\EvidenceUpload;
use App\Filament\Support\OfficeScope;
use App\Filament\Support\ReportFormParts;
use App\Models\MonthlyPlan;
use App\Models\MonthlyReport;
use App\Models\WeeklyProgressReport;
use App\Services\ReportingCalendar;
use App\Services\TargetProgress;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class MonthlyReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            ReportFormParts::returnedCallout(),
            Section::make('Reporting period')
                ->description('Results and the activity summary are filled in from this month\'s submitted weekly reports. Check and adjust them before submitting.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('department_id')
                        ->label('Office')
                        ->options(fn (): array => OfficeScope::reportingOfficeOptions())
                        ->default(fn (): ?int => self::defaultPeriod()['department_id'])
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshFromWeekly($get, $set))
                        ->disabledOn('edit')
                        ->columnSpanFull(),
                    TextInput::make('ethiopian_fiscal_year')
                        ->label('Year (E.C.)')
                        ->numeric()
                        ->minValue(1900)
                        ->maxValue(2200)
                        ->default(fn (): int => self::defaultPeriod()['year'])
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshFromWeekly($get, $set))
                        ->disabledOn('edit'),
                    Select::make('ethiopian_month')
                        ->label('Month')
                        ->options(MonthlyPlan::ETHIOPIAN_MONTHS)
                        ->default(fn (): int => self::defaultPeriod()['month'])
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshFromWeekly($get, $set))
                        ->rules([
                            fn (Get $get, ?MonthlyReport $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                $duplicate = MonthlyReport::query()
                                    ->where('department_id', $get('department_id'))
                                    ->where('ethiopian_fiscal_year', $get('ethiopian_fiscal_year'))
                                    ->where('ethiopian_month', $value)
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($duplicate) {
                                    $fail('This office already has a monthly report for this month. Open the existing report instead.');
                                }
                            },
                        ])
                        ->disabledOn('edit'),
                ]),
            Section::make('Results against monthly targets')
                ->schema([
                    TextEntry::make('no_targets')
                        ->hiddenLabel()
                        ->state('No monthly targets are allocated to this office for the selected month.')
                        ->color('warning')
                        ->visible(fn (Get $get): bool => blank($get('lines'))),
                    Repeater::make('lines')
                        ->hiddenLabel()
                        ->relationship()
                        ->default(fn (): array => self::lineRows(...array_values(self::defaultPeriod())))
                        ->addable(false)
                        ->reorderable(false)
                        ->deletable(false)
                        ->minItems(1)
                        ->validationMessages(['min' => 'This month has no targets to report against.'])
                        ->required()
                        ->itemLabel(fn (array $state): ?string => self::plan($state['monthly_plan_id'] ?? null)?->annualTarget->title)
                        ->columns(3)
                        ->schema([
                            Hidden::make('monthly_plan_id')->required(),
                            TextEntry::make('plan_context')
                                ->hiddenLabel()
                                ->state(fn (Get $get): HtmlString => self::planContext(self::plan($get('monthly_plan_id'))))
                                ->html()
                                ->columnSpan(2),
                            TextInput::make('actual_value')
                                ->label('Actual for the month')
                                ->numeric()
                                ->minValue(0)
                                ->suffix(fn (Get $get): ?string => self::plan($get('monthly_plan_id'))?->annualTarget->measurement_unit)
                                ->required()
                                ->live(onBlur: true)
                                ->helperText(fn (Get $get): ?string => self::achievementHint(self::plan($get('monthly_plan_id')), $get('actual_value'))),
                            Textarea::make('variance_note')
                                ->label(fn (Get $get): string => self::isBelowTarget(self::plan($get('monthly_plan_id')), $get('actual_value'))
                                    ? 'Why the target was missed'
                                    : 'Note (optional)')
                                ->required(fn (Get $get): bool => self::isBelowTarget(self::plan($get('monthly_plan_id')), $get('actual_value')))
                                ->validationMessages(['required' => 'Explain why the result is below the monthly target.'])
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),
                ]),
            Section::make('Monthly summary')
                ->schema([
                    Textarea::make('summary')
                        ->label('Summary of completed activities')
                        ->default(fn (): ?string => self::weeklySummary(...array_slice(array_values(self::defaultPeriod()), 0, 3)))
                        ->required()
                        ->rows(6),
                    Textarea::make('missed_target_reasons')
                        ->label('Reasons for missed targets')
                        ->rows(3),
                    Textarea::make('corrective_actions')
                        ->label('Corrective actions')
                        ->rows(3),
                    EvidenceUpload::make('monthly'),
                ]),
        ]);
    }

    /**
     * @return array{department_id: int|null, year: int, month: int}
     */
    public static function defaultPeriod(): array
    {
        $month = app(ReportingCalendar::class)->reportableMonth();
        $requestedOffice = (int) request()->query('department');

        return [
            'department_id' => $requestedOffice > 0 && array_key_exists($requestedOffice, OfficeScope::reportingOfficeOptions())
                ? $requestedOffice
                : OfficeScope::defaultOfficeId(),
            'year' => (int) (request()->query('year') ?: $month['year']),
            'month' => (int) (request()->query('month') ?: $month['month']),
        ];
    }

    /**
     * One line per monthly target, pre-filled with the combined value from submitted weekly reports.
     *
     * @return array<string, array{monthly_plan_id: int, actual_value: float|null, variance_note: null}>
     */
    public static function lineRows(?int $departmentId, ?int $year, ?int $month): array
    {
        if (! $departmentId || ! $year || ! $month) {
            return [];
        }

        $progress = app(TargetProgress::class);

        return MonthlyPlan::query()
            ->with('annualTarget')
            ->whereHas('annualTarget', fn ($query) => $query
                ->where('department_id', $departmentId)
                ->where('fiscal_year', $year)
                ->where('is_active', true))
            ->where('ethiopian_month', $month)
            ->get()
            ->mapWithKeys(fn (MonthlyPlan $plan): array => [
                (string) Str::uuid() => [
                    'monthly_plan_id' => $plan->id,
                    'actual_value' => $progress->weeklyTotalForPlan($plan),
                    'variance_note' => null,
                ],
            ])
            ->all();
    }

    /**
     * Weekly "activities completed" text for the month, to start the monthly summary.
     */
    public static function weeklySummary(?int $departmentId, ?int $year, ?int $month): ?string
    {
        if (! $departmentId || ! $year || ! $month) {
            return null;
        }

        $text = WeeklyProgressReport::query()
            ->where('department_id', $departmentId)
            ->where('ethiopian_fiscal_year', $year)
            ->where('ethiopian_month', $month)
            ->whereIn('status', ReportStatus::reportedValues())
            ->orderBy('week_number')
            ->get()
            ->map(fn (WeeklyProgressReport $report): string => "Week {$report->week_number}: {$report->achievements}")
            ->implode("\n\n");

        return $text === '' ? null : $text;
    }

    private static function refreshFromWeekly(Get $get, Set $set): void
    {
        $period = [
            (int) $get('department_id') ?: null,
            (int) $get('ethiopian_fiscal_year') ?: null,
            (int) $get('ethiopian_month') ?: null,
        ];

        $set('lines', self::lineRows(...$period));

        if (blank($get('summary'))) {
            $set('summary', self::weeklySummary(...$period));
        }
    }

    private static function plan(mixed $planId): ?MonthlyPlan
    {
        if (blank($planId)) {
            return null;
        }

        return once(fn () => MonthlyPlan::with('annualTarget')->find($planId));
    }

    private static function planContext(?MonthlyPlan $plan): HtmlString
    {
        if ($plan === null) {
            return new HtmlString('');
        }

        return new HtmlString(view('filament.reports.plan-context', [
            'target' => $plan->annualTarget,
            'plannedValue' => $plan->planned_value,
            'reported' => app(TargetProgress::class)->weeklyTotalForPlan($plan),
            'reportedLabel' => 'Weekly reports total',
            'activities' => null,
        ])->render());
    }

    private static function isBelowTarget(?MonthlyPlan $plan, mixed $actual): bool
    {
        return $plan !== null && is_numeric($actual) && (float) $actual < (float) $plan->planned_value;
    }

    private static function achievementHint(?MonthlyPlan $plan, mixed $actual): ?string
    {
        if ($plan === null || ! is_numeric($actual)) {
            return null;
        }

        $percentage = TargetProgress::achievement((float) $actual, $plan->planned_value);

        return $percentage === null ? null : "{$percentage}% of the monthly target.";
    }
}
