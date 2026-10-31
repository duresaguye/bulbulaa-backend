<?php

namespace App\Filament\Resources\AnnualTargets\Pages;

use App\Filament\Resources\AnnualTargets\AnnualTargetResource;
use App\Filament\Support\OfficeScope;
use App\Models\AnnualTarget;
use App\Services\TargetAssignment;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditAnnualTarget extends EditRecord
{
    protected static string $resource = AnnualTargetResource::class;

    public function getTitle(): string
    {
        return 'Edit target';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * A change to the target value goes through TargetAssignment so the revision and its reason are recorded.
     *
     * @param  AnnualTarget  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $newValue = (float) $data['target_value'];
        $reason = (string) ($data['revision_reason'] ?? '');
        unset($data['target_value'], $data['revision_reason']);

        DB::transaction(function () use ($record, $data, $newValue, $reason): void {
            $record->update($data);

            if ($newValue !== (float) $record->target_value) {
                app(TargetAssignment::class)->revise(OfficeScope::user(), $record, $newValue, $reason);
            }
        });

        return $record;
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
