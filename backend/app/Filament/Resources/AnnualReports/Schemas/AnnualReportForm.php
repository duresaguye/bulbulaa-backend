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
            Section::make('Reporting year')
                ->columns(3)
                ->schema([
                    Select::make('department_id')
                        ->label('Office')
                        ->options(fn (): array => OfficeScope::reportingOfficeOptions())
                        ->default(fn (): ?int => OfficeScope::defaultOfficeId())
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->columnSpan(2),
                    TextInput::make('ethiopian_fiscal_year')
                        ->label('Year (E.C.)')
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
                                    $fail('This office already has an annual report for this year.');
                                }
                            },
                        ])
                        ->disabledOn('edit'),
                ]),
            Section::make('Results for the year')
                ->description('Calculated from approved monthly reports only. The figures are recorded with the report when it is submitted.')
                ->schema([
                    View::make('filament.reports.annual-results')
                        ->viewData(fn (Get $get): array => [
                            'results' => self::results($get('department_id'), $get('ethiopian_fiscal_year')),
                        ]),
                ]),
            Section::make('Annual review')
                ->schema([
                    Textarea::make('key_accomplishments')
                        ->label('Key accomplishments')
                        ->required()
                        ->rows(5),
                    Textarea::make('missed_targets')
                        ->label('Missed targets and explanations')
                        ->rows(4),
                    Textarea::make('corrective_actions')
                        ->label('Corrective actions')
                        ->rows(3),
                    Textarea::make('lessons_learned')
                        ->label('Lessons learned')
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
