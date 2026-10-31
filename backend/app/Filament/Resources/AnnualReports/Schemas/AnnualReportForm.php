<?php

namespace App\Filament\Resources\AnnualReports\Schemas;

use App\Filament\Support\EvidenceUpload;
use App\Filament\Support\OfficeScope;
use App\Filament\Support\ReportFormParts;
use App\Models\AnnualReport;
use App\Models\Department;
use App\Services\EthiopianCalendar;
use App\Services\ReportWorkflow;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class AnnualReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            ReportFormParts::returnedCallout(),
            Section::make(__('Reporting year'))
                ->columns(3)
                ->schema([
                    Select::make('department_id')
                        ->label(__('Office'))
                        ->options(fn (): array => OfficeScope::reportingOfficeOptions())
                        ->default(fn (): ?int => OfficeScope::defaultOfficeId())
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->columnSpan(2),
                    TextInput::make('ethiopian_fiscal_year')
                        ->label(__('Year (E.C.)'))
                        ->numeric()
                        ->minValue(1900)
                        ->maxValue(2200)
                        ->default(fn (): int => (int) (request()->query('year') ?: EthiopianCalendar::today()['year']))
                        ->required()
                        ->live(onBlur: true)
                        ->rules([
                            fn (Get $get, ?AnnualReport $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                $duplicate = AnnualReport::query()
                                    ->where('department_id', $get('department_id'))
                                    ->where('ethiopian_fiscal_year', $value)
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($duplicate) {
                                    $fail(__('This office already has an annual report for this year.'));
                                }
                            },
                        ])
                        ->disabledOn('edit'),
                ]),
            Section::make(__('Results for the year'))
                ->description(__('Calculated from approved monthly reports only. The figures are recorded with the report when it is submitted.'))
                ->schema([
                    View::make('filament.reports.annual-results')
                        ->viewData(fn (Get $get): array => [
                            'results' => self::results($get('department_id'), $get('ethiopian_fiscal_year')),
                        ]),
                ]),
            Section::make(__('Annual review'))
                ->schema([
                    Textarea::make('key_accomplishments')
                        ->label(__('Key accomplishments'))
                        ->required()
                        ->rows(5),
                    Textarea::make('missed_targets')
                        ->label(__('Missed targets and explanations'))
                        ->rows(4),
                    Textarea::make('corrective_actions')
                        ->label(__('Corrective actions'))
                        ->rows(3),
                    Textarea::make('lessons_learned')
                        ->label(__('Lessons learned'))
                        ->rows(3),
                    EvidenceUpload::make('annual'),
                ]),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function results(mixed $departmentId, mixed $year): array
    {
        $department = filled($departmentId) ? Department::find($departmentId) : null;

        if ($department === null || ! is_numeric($year)) {
            return [];
        }

        return app(ReportWorkflow::class)->annualResults($department, (int) $year);
    }
}
