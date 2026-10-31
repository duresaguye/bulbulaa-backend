<?php

namespace App\Filament\Resources\CitizenReports\Pages;

use App\Enums\CitizenReportStatus;
use App\Filament\Resources\CitizenReports\CitizenReportResource;
use App\Models\CitizenReport;
use App\Models\Department;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewCitizenReport extends ViewRecord
{
    protected static string $resource = CitizenReportResource::class;

    public function getTitle(): string
    {
        /** @var CitizenReport $report */
        $report = $this->getRecord();

        return $report->category->getLabel().' '.$report->reference;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('handle')
                ->label(__('Update'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->fillForm(fn (CitizenReport $record): array => [
                    'status' => $record->status === CitizenReportStatus::New ? CitizenReportStatus::InProgress : $record->status,
                    'department_id' => $record->department_id,
                    'staff_note' => $record->staff_note,
                ])
                ->schema([
                    Select::make('status')->label(__('Status'))->options(CitizenReportStatus::class)->required(),
                    Select::make('department_id')
                        ->label(__('Office handling it'))
                        ->options(fn (): array => Department::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    Textarea::make('staff_note')
                        ->label(__('Internal note'))
                        ->helperText(__('Only staff see this. Write what was done or who is following up.'))
                        ->rows(4)
                        ->maxLength(2000),
                ])
                ->action(function (CitizenReport $record, array $data): void {
                    $record->update([...$data, 'handled_by' => auth()->id()]);
                    Notification::make()->success()->title(__('Message updated'))->send();
                }),
            DeleteAction::make(),
        ];
    }
}
