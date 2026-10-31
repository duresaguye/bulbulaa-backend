<?php

namespace App\Filament\Resources\MonthlyReports\Pages;

use App\Filament\Resources\MonthlyReports\MonthlyReportResource;
use App\Filament\Support\Concerns\ViewsOfficeReport;
use App\Models\MonthlyReportLine;
use App\Services\TargetProgress;
use Filament\Resources\Pages\ViewRecord;

class ViewMonthlyReport extends ViewRecord
{
    use ViewsOfficeReport;

    protected static string $resource = MonthlyReportResource::class;

    public function resultsTable(): ?array
    {
        $lines = $this->getRecord()->lines()->with('monthlyPlan.annualTarget')->get();

        return [
            'heading' => __('Results against monthly targets'),
            'target_label' => __('Monthly target'),
            'actual_label' => __('Actual'),
            'rows' => $lines->map(function (MonthlyReportLine $line): array {
                $target = $line->monthlyPlan->annualTarget;

                return [
                    'title' => $target->title,
                    'unit' => $target->measurement_unit,
                    'target' => $target->formatValue($line->monthlyPlan->planned_value),
                    'actual' => $target->formatValue($line->actual_value),
                    'achievement' => TargetProgress::achievement((float) $line->actual_value, $line->monthlyPlan->planned_value),
                    'note' => $line->variance_note,
                ];
            })->all(),
        ];
    }

    public function narrative(): array
    {
        $record = $this->getRecord();

        return [
            ['label' => __('Summary of completed activities'), 'value' => $record->summary],
            ['label' => __('Reasons for missed targets'), 'value' => $record->missed_target_reasons],
            ['label' => __('Corrective actions'), 'value' => $record->corrective_actions],
        ];
    }
}
