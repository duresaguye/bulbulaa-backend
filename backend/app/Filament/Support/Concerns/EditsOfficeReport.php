<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\OfficeScope;
use App\Services\ReportWorkflow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Editing is only possible while a report is a draft or returned; saving can also resubmit it.
 *
 * @mixin EditRecord
 */
trait EditsOfficeReport
{
    public bool $submitAfterSaving = false;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->label(__('Delete draft')),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label(__('Save draft'))
                ->color('gray'),
            Action::make('saveAndSubmit')
                ->label(fn (): string => $this->getRecord()->status->value === 'returned' ? __('Save and resubmit') : __('Save and submit for review'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => OfficeScope::user() !== null
                    && app(ReportWorkflow::class)->canSubmit(OfficeScope::user(), $this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading(__('Submit this report?'))
                ->modalDescription(fn (): string => __('Your changes will be saved and the report sent for review. ')
                    .ReportWorkflow::reviewerDescription($this->getRecord()->department))
                ->modalSubmitActionLabel(__('Submit for review'))
                ->action(function (): void {
                    $this->submitAfterSaving = true;
                    $this->save();
                }),
            $this->getCancelFormAction(),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless($this->getRecord()->isEditable(), 403);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        abort_unless($record->isEditable() && OfficeScope::canWriteFor((int) $record->department_id), 403);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->submitAfterSaving) {
            app(ReportWorkflow::class)->submit(OfficeScope::user(), $this->getRecord()->refresh());
        }
    }

    protected function getSavedNotification(): ?Notification
    {
        $record = $this->getRecord();

        return Notification::make()
            ->success()
            ->title($this->submitAfterSaving ? __('Report submitted') : __('Draft saved'))
            ->body($this->submitAfterSaving ? ReportWorkflow::reviewerDescription($record->department) : null);
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
