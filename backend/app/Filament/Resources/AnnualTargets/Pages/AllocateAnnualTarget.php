<?php

namespace App\Filament\Resources\AnnualTargets\Pages;

use App\Filament\Resources\AnnualTargets\AnnualTargetResource;
use App\Filament\Support\OfficeScope;
use App\Models\AnnualTarget;
use App\Models\MonthlyPlan;
use App\Services\TargetAssignment;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Split an annual target into monthly targets on one screen, with a running total against the annual figure.
 *
 * @property-read Schema $form
 */
class AllocateAnnualTarget extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AnnualTargetResource::class;

    protected string $view = 'filament.targets.allocate';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(
            OfficeScope::user() !== null && app(TargetAssignment::class)->canAllocate(OfficeScope::user(), $this->getRecord()),
            403,
        );

        $plans = $this->getRecord()->monthlyPlans()->get()->keyBy('ethiopian_month');

        $this->form->fill([
            'months' => collect(MonthlyPlan::ETHIOPIAN_MONTHS)->mapWithKeys(fn (string $name, int $month): array => [
                $month => [
                    'planned_value' => $plans->get($month)?->planned_value,
                    'planned_activities' => $plans->get($month)?->planned_activities,
                ],
            ])->all(),
        ]);
    }

    public function getTitle(): string
    {
        return 'Allocate across months';
    }

    public function getSubheading(): string
    {
        $target = $this->getRecord();

        return $target->title.', '.$target->department->name.', year '.$target->fiscal_year.' E.C.';
    }

    public function form(Schema $schema): Schema
    {
        /** @var AnnualTarget $target */
        $target = $this->getRecord();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Annual target: '.$target->formatValue($target->target_value).' '.$target->measurement_unit)
                    ->description($target->aggregation->getDescription().' Leave a month blank if no work is planned.')
                    ->schema([
                        ...collect(MonthlyPlan::ETHIOPIAN_MONTHS)->map(fn (string $name, int $month) => Grid::make(['default' => 1, 'md' => 6])
                            ->schema([
                                TextInput::make("months.{$month}.planned_value")
                                    ->label($name)
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix($target->measurement_unit)
                                    ->live(onBlur: true)
                                    ->columnSpan(['md' => 2]),
                                TextInput::make("months.{$month}.planned_activities")
                                    ->label('Planned activities')
                                    ->maxLength(1000)
                                    ->columnSpan(['md' => 4]),
                            ]))->values()->all(),
                        TextEntry::make('reconciliation')
                            ->label('Allocation check')
                            ->state(fn (Get $get): string => $this->reconciliationText($get('months') ?? []))
                            ->color(fn (Get $get): string => $this->reconciles($get('months') ?? []) ? 'success' : 'warning'),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label('Save allocation')
                        ->submit('save'),
                    Action::make('cancel')
                        ->label('Cancel')
                        ->color('gray')
                        ->url(AnnualTargetResource::getUrl('view', ['record' => $target])),
                ]),
            ]);
    }

    public function save(): void
    {
        $months = $this->form->getState()['months'] ?? [];
        $target = $this->getRecord();

        app(TargetAssignment::class)->allocate(OfficeScope::user(), $target, $months);

        $notification = Notification::make()->title('Monthly allocation saved');

        if ($this->reconciles($months)) {
            $notification->success();
        } else {
            $notification->warning()->body($this->reconciliationText($months));
        }

        $notification->send();

        $this->redirect(AnnualTargetResource::getUrl('view', ['record' => $target]));
    }

    /**
     * @param  array<int|string, array{planned_value?: mixed}>  $months
     */
    private function allocatedTotal(array $months): float
    {
        return (float) collect($months)->pluck('planned_value')->filter(fn ($value) => is_numeric($value))->sum();
    }

    /**
     * @param  array<int|string, array{planned_value?: mixed}>  $months
     */
    private function reconciles(array $months): bool
    {
        $target = $this->getRecord();

        return ! $target->aggregation->isAdditive()
            || round($this->allocatedTotal($months), 2) === round((float) $target->target_value, 2);
    }

    /**
     * @param  array<int|string, array{planned_value?: mixed}>  $months
     */
    private function reconciliationText(array $months): string
    {
        $target = $this->getRecord();

        if (! $target->aggregation->isAdditive()) {
            return 'Monthly values for this indicator are levels or rates, so they are not added together.';
        }

        $total = $this->allocatedTotal($months);
        $gap = (float) $target->target_value - $total;

        return match (true) {
            round($gap, 2) == 0 => 'Monthly targets total '.$target->formatValue($total).', matching the annual target.',
            $gap > 0 => 'Monthly targets total '.$target->formatValue($total).'. '.$target->formatValue($gap).' of the annual target is not yet allocated.',
            default => 'Monthly targets total '.$target->formatValue($total).', which is '.$target->formatValue(abs($gap)).' more than the annual target.',
        };
    }
}
