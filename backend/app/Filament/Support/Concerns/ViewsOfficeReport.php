<?php

namespace App\Filament\Support\Concerns;

use App\Enums\ReportStatus;
use App\Filament\Support\ExportActions;
use App\Filament\Support\ReportActions;
use App\Services\ReportWorkflow;
use App\Support\ReportDocument;
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
        return __(':report for :period', ['report' => ucfirst(static::getResource()::getModelLabel()), 'period' => $this->getRecord()->periodLabel()]);
    }

    protected function getHeaderActions(): array
    {
        $type = ReportDocument::typeForPage(static::class);
        $route = fn (string $format): string => route('filament.admin.exports.report.'.$format, ['type' => $type, 'record' => $this->getRecord()]);

        return [
            ExportActions::group(fn (): string => $route('print'), fn (): string => $route('excel')),
            ReportActions::approve(),
            ReportActions::return(),
            ReportActions::submit(),
            EditAction::make()
                ->label(fn (): string => $this->getRecord()->status === ReportStatus::Returned ? __('Correct report') : __('Edit draft'))
                ->visible(fn (): bool => static::getResource()::canEdit($this->getRecord())),
        ];
    }

    public function nextStep(): string
    {
        $record = $this->getRecord();

        return match ($record->status) {
            ReportStatus::Draft => __('Not yet submitted. Submit it when the figures are complete.'),
            ReportStatus::Submitted => ReportWorkflow::reviewerDescription($record->department),
            ReportStatus::Returned => __('Correct the report and submit it again.'),
            ReportStatus::Approved => __('Approved. This report is part of the official record.'),
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
