<?php

namespace App\Filament\Resources\WeeklyProgressReports\Schemas;

use App\Filament\Support\EvidenceUpload;
use App\Filament\Support\OfficeScope;
use App\Filament\Support\ReportFormParts;
use App\Models\MonthlyPlan;
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

class WeeklyProgressReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            ReportFormParts::returnedCallout(),
            Section::make('Reporting period')
                ->description('The office and Ethiopian calendar week this update covers. The period cannot change once results are saved.')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('department_id')
                        ->label('Office')
                        ->options(fn (): array => OfficeScope::reportingOfficeOptions())
                        ->default(fn (): ?int => self::defaultPeriod()['department_id'])
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshEntries($get, $set))
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
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshEntries($get, $set))
                        ->disabledOn('edit'),
                    Select::make('ethiopian_month')
                        ->label('Month')
                        ->options(MonthlyPlan::ETHIOPIAN_MONTHS)
                        ->default(fn (): int => self::defaultPeriod()['month'])
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshEntries($get, $set))
                        ->disabledOn('edit'),
                    Select::make('week_number')
                        ->label('Week')
                        ->options([1 => 'Week 1 (days 1–7)', 2 => 'Week 2 (days 8–14)', 3 => 'Week 3 (days 15–21)', 4 => 'Week 4 (days 22–28)', 5 => 'Week 5 (days 29–30)'])
                        ->default(fn (): int => self::defaultPeriod()['week'])
                        ->required()
                        ->rules([
                            fn (Get $get, ?WeeklyProgressReport $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                $duplicate = WeeklyProgressReport::query()
                                    ->where('department_id', $get('department_id'))
                                    ->where('ethiopian_fiscal_year', $get('ethiopian_fiscal_year'))
                                    ->where('ethiopian_month', $get('ethiopian_month'))
                                    ->where('week_number', $value)
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($duplicate) {
                                    $fail('This office already has a report for this week. Open the existing report instead.');
                                }
                            },
                        ])
                        ->disabledOn('edit'),
                ]),
            Section::make('Results against this month\'s targets')
                ->description('One row for each monthly target. Enter what was achieved this week only. Remove a row if there was no work on that target.')
                ->schema([
                    TextEntry::make('no_targets')
                        ->hiddenLabel()
                        ->state('No monthly targets are allocated to this office for the selected month. The office head allocates annual targets across months under Annual targets.')
                        ->color('warning')
                        ->visible(fn (Get $get): bool => blank($get('entries'))),
                    Repeater::make('entries')
                        ->hiddenLabel()
                        ->relationship()
                        ->default(fn (): array => self::entryRows(...array_values(self::defaultPeriod())))
                        ->addable(false)
                        ->reorderable(false)
                        ->minItems(1)
                        ->validationMessages(['min' => 'Record a result for at least one monthly target.'])
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
                                ->label('Achieved this week')
                                ->numeric()
                                ->minValue(0)
                                ->suffix(fn (Get $get): ?string => self::plan($get('monthly_plan_id'))?->annualTarget->measurement_unit)
                                ->required()
                                ->live(onBlur: true)
                                ->helperText(fn (Get $get): ?string => self::achievementHint(self::plan($get('monthly_plan_id')), $get('actual_value'))),
                            Textarea::make('progress_summary')
                                ->label('Note (optional)')
                                ->placeholder('What was done, or why the result differs from plan')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columnSpanFull(),
                ]),
            Section::make('This week')
                ->schema([
                    Textarea::make('achievements')
                        ->label('Activities completed')
                        ->required()
                        ->rows(4),
                    Textarea::make('challenges')
                        ->label('Challenges or delays')
                        ->rows(3),
                    Textarea::make('next_steps')
                        ->label('Priorities for next week')
                        ->rows(3),
                    EvidenceUpload::make('weekly'),
                ]),
        ]);
    }

    /**
     * Period suggested for a new report: query string values from dashboard links, otherwise the most recent week.
     *
     * @return array{department_id: int|null, year: int, month: int, week: int}
     */
    public static function defaultPeriod(): array
    {
        $calendar = app(ReportingCalendar::class);
        $week = $calendar->reportableWeek();
        $requestedOffice = (int) request()->query('department');
        $officeId = $requestedOffice > 0 && array_key_exists($requestedOffice, OfficeScope::reportingOfficeOptions())
            ? $requestedOffice
            : OfficeScope::defaultOfficeId();

        /** Once last week is reported, suggest the week in progress instead. */
        $lastWeekReported = $officeId !== null && WeeklyProgressReport::query()
            ->where('department_id', $officeId)
            ->where('ethiopian_fiscal_year', $week['year'])
            ->where('ethiopian_month', $week['month'])
            ->where('week_number', $week['week'])
            ->exists();

        if ($lastWeekReported) {
            $week = ['year' => $calendar->today['year'], 'month' => $calendar->today['month'], 'week' => $calendar->today['week']];
        }

        return [
            'department_id' => $officeId,
            'year' => (int) (request()->query('year') ?: $week['year']),
            'month' => (int) (request()->query('month') ?: $week['month']),
            'week' => (int) (request()->query('week') ?: $week['week']),
        ];
    }

    /**
     * @return array<string, array{monthly_plan_id: int, actual_value: null, progress_summary: null}>
     */
    public static function entryRows(?int $departmentId, ?int $year, ?int $month, ?int $week = null): array
    {
        if (! $departmentId || ! $year || ! $month) {
            return [];
        }

        return MonthlyPlan::query()
            ->whereHas('annualTarget', fn ($query) => $query
                ->where('department_id', $departmentId)
                ->where('fiscal_year', $year)
                ->where('is_active', true))
            ->where('ethiopian_month', $month)
            ->get()
            ->mapWithKeys(fn (MonthlyPlan $plan): array => [
                (string) Str::uuid() => ['monthly_plan_id' => $plan->id, 'actual_value' => null, 'progress_summary' => null],
            ])
            ->all();
    }

    private static function refreshEntries(Get $get, Set $set): void
    {
        $set('entries', self::entryRows(
            (int) $get('department_id') ?: null,
            (int) $get('ethiopian_fiscal_year') ?: null,
            (int) $get('ethiopian_month') ?: null,
        ));
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
            'reportedLabel' => 'Reported in earlier weeks',
            'activities' => $plan->planned_activities,
        ])->render());
    }

    private static function achievementHint(?MonthlyPlan $plan, mixed $actual): ?string
    {
        if ($plan === null || ! is_numeric($actual) || ! $plan->annualTarget->aggregation->isAdditive()) {
            return null;
        }

        $percentage = TargetProgress::achievement((float) $actual, $plan->planned_value);

        return $percentage === null ? null : "{$percentage}% of this month's target.";
    }
}
