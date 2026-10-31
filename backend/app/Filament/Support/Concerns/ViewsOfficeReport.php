<?php

namespace App\Filament\Support\Concerns;

use App\Enums\ReportStatus;
use App\Filament\Support\ReportActions;
use App\Services\ReportWorkflow;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

/**
 * Read view for an office report, with the workflow actions the signed-in user is allowed to take.
 *
 * @mixin ViewRecord
 */
trait ViewsOfficeReport
{
    public function getView(): string
    {
        return 'filament.reports.view';
    }

    /**
     * @return array{heading: string, target_label: string, actual_label: string, rows: list<array{title: string, unit: string, target: string, actual: string, achievement: float|null, note: string|null}>}|null
     */
    abstract public function resultsTable(): ?array;

    /**
     * @return list<array{label: string, value: string|null}>
     */
    abstract public function narrative(): array;

    public function getTitle(): string
    {
        return $this->getRecord()->department->name;
    }

    public function getSubheading(): string
    {
        return ucfirst(static::getResource()::getModelLabel()).' for '.$this->getRecord()->periodLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            ReportActions::approve(),
            ReportActions::return(),
            ReportActions::submit(),
            EditAction::make()
                ->label(fn (): string => $this->getRecord()->status === ReportStatus::Returned ? 'Correct report' : 'Edit draft')
                ->visible(fn (): bool => static::getResource()::canEdit($this->getRecord())),
        ];
    }

    public function nextStep(): string
    {
        $record = $this->getRecord();

        return match ($record->status) {
            ReportStatus::Draft => 'Not yet submitted. Submit it when the figures are complete.',
            ReportStatus::Submitted => ReportWorkflow::reviewerDescription($record->department),
            ReportStatus::Returned => 'Correct the report and submit it again.',
            ReportStatus::Approved => 'Approved. This report is part of the official record.',
        };
    }

    /**
     * @return list<array{name: string, url: string|null}>
     */
    public function evidence(): array
    {
        $disk = Storage::disk('local');

        return collect($this->getRecord()->evidence_paths ?? [])
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'url' => $disk->exists($path) ? $disk->temporaryUrl($path, now()->addMinutes(30)) : null,
            ])
            ->all();
    }
}
