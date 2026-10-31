<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\OfficeScope;
use App\Services\ReportWorkflow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

/**
 * "Save draft" and "Save and submit" for new office reports.
 *
 * @mixin CreateRecord
 */
trait CreatesOfficeReport
{
    public bool $submitAfterSaving = false;

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Save draft')
                ->color('gray'),
            Action::make('createAndSubmit')
                ->label('Save and submit for review')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => OfficeScope::user()?->hasPermission('reports.submit') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Submit this report?')
                ->modalDescription('The report will be saved and sent to the supervising office for review. You cannot edit it afterwards unless it is returned for correction.')
                ->modalSubmitActionLabel('Submit for review')
                ->action(function (): void {
                    $this->submitAfterSaving = true;
                    $this->create();
                }),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(OfficeScope::canWriteFor((int) $data['department_id']), 403);

        $data['prepared_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->submitAfterSaving) {
            return;
        }

        $workflow = app(ReportWorkflow::class);
        $record = $this->getRecord()->refresh();

        if (! $workflow->canSubmit(OfficeScope::user(), $record)) {
            Notification::make()
                ->warning()
                ->title('Saved as a draft')
                ->body("Only staff of {$record->department->name} who are allowed to submit reports can send it for review.")
                ->send();

            return;
        }

        $workflow->submit(OfficeScope::user(), $record);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->submitAfterSaving ? 'Report submitted' : 'Draft saved';
    }

    protected function getCreatedNotification(): ?Notification
    {
        $record = $this->getRecord();

        return Notification::make()
            ->success()
            ->title($this->getCreatedNotificationTitle())
            ->body($record->isEditable()
                ? 'Your office can continue editing it until it is submitted.'
                : ReportWorkflow::reviewerDescription($record->department));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
