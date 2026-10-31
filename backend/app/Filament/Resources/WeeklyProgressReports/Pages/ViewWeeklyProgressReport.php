<?php

namespace App\Filament\Resources\WeeklyProgressReports\Pages;

use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use App\Filament\Support\Concerns\ViewsOfficeReport;
use App\Models\WeeklyProgressEntry;
use App\Services\TargetProgress;
use Filament\Resources\Pages\ViewRecord;

class ViewWeeklyProgressReport extends ViewRecord
{
    use ViewsOfficeReport;

    protected static string $resource = WeeklyProgressReportResource::class;

    public function resultsTable(): ?array
    {
        $entries = $this->getRecord()->entries()->with('monthlyPlan.annualTarget')->get();

        return [
            'heading' => __('Results against monthly targets'),
            'target_label' => __('Monthly target'),
            'actual_label' => __('Achieved this week'),
            'rows' => $entries->map(function (WeeklyProgressEntry $entry): array {
                $target = $entry->monthlyPlan->annualTarget;

                return [
                    'title' => $target->title,
                    'unit' => $target->measurement_unit,
                    'target' => $target->formatValue($entry->monthlyPlan->planned_value),
                    'actual' => $target->formatValue($entry->actual_value),
                    'achievement' => $target->aggregation->isAdditive()
                        ? TargetProgress::achievement((float) $entry->actual_value, $entry->monthlyPlan->planned_value)
                        : null,
                    'note' => $entry->progress_summary,
                ];
            })->all(),
        ];
    }

    public function narrative(): array
    {
        $record = $this->getRecord();

        return [
            ['label' => __('Activities completed'), 'value' => $record->achievements],
            ['label' => __('Challenges or delays'), 'value' => $record->challenges],
            ['label' => __('Priorities for next week'), 'value' => $record->next_steps],
        ];
    }
}
