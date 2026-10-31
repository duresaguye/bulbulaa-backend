<?php

namespace App\Filament\Resources\AnnualTargets\Pages;

use App\Filament\Resources\AnnualTargets\AnnualTargetResource;
use App\Filament\Resources\AnnualTargets\Tables\AnnualTargetsTable;
use App\Filament\Support\OfficeScope;
use App\Models\AnnualTarget;
use App\Models\MonthlyReportLine;
use App\Services\EthiopianCalendar;
use App\Services\TargetAssignment;
use App\Services\TargetProgress;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property AnnualTarget $record
 */
class ViewAnnualTarget extends ViewRecord
{
    protected static string $resource = AnnualTargetResource::class;

    protected string $view = 'filament.targets.view';

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    public function getSubheading(): string
    {
        $target = $this->getRecord();

        return $target->department->name.', year '.$target->fiscal_year.' E.C.'
            .($target->parent_id ? '. Share of '.$target->parent->department->name.'\'s target.' : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allocate')
                ->label('Allocate across months')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->visible(fn (): bool => OfficeScope::user() !== null
                    && app(TargetAssignment::class)->canAllocate(OfficeScope::user(), $this->getRecord()))
                ->url(fn (): string => AnnualTargetResource::getUrl('allocate', ['record' => $this->getRecord()])),
            $this->distributeAction(),
            EditAction::make()->label('Edit or revise')->color('gray'),
        ];
    }

    private function distributeAction(): Action
    {
        return Action::make('distribute')
            ->label('Distribute to offices')
            ->icon(Heroicon::OutlinedArrowsPointingOut)
            ->color('gray')
            ->visible(fn (): bool => OfficeScope::user() !== null
                && app(TargetAssignment::class)->canDistribute(OfficeScope::user(), $this->getRecord()))
            ->modalHeading('Distribute to subordinate offices')
            ->modalDescription(function (): string {
                $target = $this->getRecord();
                $text = 'Annual target '.$target->formatValue($target->target_value).' '.$target->measurement_unit.'.';

                return $target->aggregation->isAdditive()
                    ? $text.' Already distributed: '.$target->formatValue($target->distributedTotal()).'.'
                    : $text.' This indicator is a rate or level, so each office gets its own value rather than a share of a total.';
            })
            ->schema([
                Repeater::make('shares')
                    ->hiddenLabel()
                    ->addActionLabel('Add office')
                    ->minItems(1)
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->label('Office')
                            ->options(fn (): array => app(TargetAssignment::class)->distributionOptions(OfficeScope::user(), $this->getRecord()))
                            ->distinct()
                            ->required(),
                        TextInput::make('target_value')
                            ->label('Office target')
                            ->suffix(fn (): string => $this->getRecord()->measurement_unit)
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
            ])
            ->modalSubmitActionLabel('Assign to offices')
            ->action(function (array $data): void {
                $target = $this->getRecord();
                app(TargetAssignment::class)->distribute(OfficeScope::user(), $target, $data['shares']);
                $target->load('children');

                $notification = Notification::make()->success()->title('Targets assigned to offices');
                $gap = $target->aggregation->isAdditive() ? (float) $target->target_value - $target->distributedTotal() : 0.0;

                if ($gap != 0) {
                    $notification->warning()->body($gap > 0
                        ? $target->formatValue($gap).' of the annual target is not yet distributed.'
                        : 'Office targets exceed the annual target by '.$target->formatValue(abs($gap)).'.');
                }

                $notification->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function targetDetails(): array
    {
        $target = $this->getRecord()->load(['monthlyPlans', 'children.department', 'children.monthlyPlans', 'revisions.revisedBy', 'assignedBy']);
        $progress = app(TargetProgress::class);
        $asOfMonth = AnnualTargetsTable::asOfMonth($target->fiscal_year);
        $actuals = $progress->monthlyActuals($target);
        $monthlySource = MonthlyReportLine::query()
            ->whereIn('monthly_plan_id', $target->monthlyPlans->pluck('id'))
            ->whereHas('report', fn ($query) => $query->reported())
            ->with('report:id,status')
            ->get()
            ->keyBy('monthly_plan_id');

        $quarters = $target->monthlyPlans
            ->groupBy(fn ($plan) => EthiopianCalendar::quarterOfMonth($plan->ethiopian_month))
            ->map(function ($plans) use ($target, $actuals, $monthlySource): array {
                $rows = $plans->map(fn ($plan): array => [
                    'month' => EthiopianCalendar::monthName($plan->ethiopian_month),
                    'planned' => (float) $plan->planned_value,
                    'actual' => $actuals[$plan->ethiopian_month] ?? null,
                    'activities' => $plan->planned_activities,
                    'source' => $monthlySource->has($plan->id)
                        ? 'Monthly report, '.strtolower($monthlySource[$plan->id]->report->status->getLabel())
                        : (($actuals[$plan->ethiopian_month] ?? null) !== null ? 'Weekly reports' : null),
                ])->values();

                $actualValues = $rows->pluck('actual')->filter(fn ($value) => $value !== null)->values()->all();

                return [
                    'rows' => $rows,
                    'planned' => $target->aggregation->isAdditive()
                        ? (float) $rows->sum('planned')
                        : (float) $rows->last()['planned'],
                    'actual' => $target->aggregation->combine($actualValues),
                ];
            });

        return [
            'target' => $target,
            'summary' => $progress->summary($target, $asOfMonth),
            'asOfMonth' => $asOfMonth,
            'quarters' => $quarters,
            'children' => $target->children->map(fn (AnnualTarget $child): array => [
                'target' => $child,
                'summary' => $progress->summary($child, $asOfMonth),
            ]),
        ];
    }
}
