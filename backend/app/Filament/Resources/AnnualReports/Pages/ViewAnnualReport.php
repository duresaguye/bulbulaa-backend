<?php

namespace App\Filament\Resources\AnnualReports\Pages;

use App\Filament\Resources\AnnualReports\AnnualReportResource;
use App\Filament\Support\Concerns\ViewsOfficeReport;
use App\Services\ReportWorkflow;
use Filament\Resources\Pages\ViewRecord;

class ViewAnnualReport extends ViewRecord
{
    use ViewsOfficeReport;

    protected static string $resource = AnnualReportResource::class;

    /**
     * Submitted reports show the figures recorded at submission; drafts show the live calculation.
     */
    public function resultsTable(): ?array
    {
        $record = $this->getRecord();
        $results = $record->results_snapshot
            ?? app(ReportWorkflow::class)->annualResults($record->department, $record->ethiopian_fiscal_year);
        $format = fn (?float $value): string => $value === null ? '—' : number_format($value, floor($value) == $value ? 0 : 2);

        return [
            'heading' => $record->results_snapshot === null
                ? 'Results for the year (from approved monthly reports, recalculated until submission)'
                : 'Results for the year (as recorded at submission)',
            'target_label' => 'Target',
            'actual_label' => 'Approved result',
            'rows' => collect($results)->map(fn (array $row): array => [
                'title' => $row['title'],
                'unit' => $row['unit'],
                'target' => $format($row['current_target']),
                'actual' => $format($row['actual']),
                'achievement' => $row['achievement'],
                'note' => $row['current_target'] != $row['original_target']
                    ? 'Originally assigned: '.$format($row['original_target'])
                    : null,
            ])->all(),
        ];
    }

    public function narrative(): array
    {
        $record = $this->getRecord();

        return [
            ['label' => 'Key accomplishments', 'value' => $record->key_accomplishments],
            ['label' => 'Missed targets and explanations', 'value' => $record->missed_targets],
            ['label' => 'Corrective actions', 'value' => $record->corrective_actions],
            ['label' => 'Lessons learned', 'value' => $record->lessons_learned],
        ];
    }
}
