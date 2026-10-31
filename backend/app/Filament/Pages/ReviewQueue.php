<?php

namespace App\Filament\Pages;

use App\Enums\ReportStatus;
use App\Filament\Resources\AnnualReports\AnnualReportResource;
use App\Filament\Resources\MonthlyReports\MonthlyReportResource;
use App\Filament\Resources\WeeklyProgressReports\WeeklyProgressReportResource;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\OfficeScope;
use App\Filament\Support\ReportTableParts;
use App\Models\AnnualReport;
use App\Models\MonthlyReport;
use App\Models\WeeklyProgressReport;
use App\Services\OfficeOverview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

/**
 * Reports from subordinate offices that are waiting for the signed-in user's decision.
 */
class ReviewQueue extends Page implements HasTable
{
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Oversight';

    protected static ?string $navigationLabel = 'Review queue';

    protected static ?string $title = 'Review queue';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.review-queue';

    #[Url]
    public string $type = 'weekly';

    public static function canAccess(): bool
    {
        return OfficeScope::user()?->hasPermission('reports.review') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = OfficeScope::user();
        $count = $user === null ? 0 : app(OfficeOverview::class)->reviewQueueCount($user);

        return $count > 0 ? (string) $count : null;
    }

    public function getSubheading(): string
    {
        return __('Reports from subordinate offices waiting for your decision. You never see reports you prepared or submitted yourself.');
    }

    /**
     * @return array<string, array{label: string, count: int}>
     */
    public function tabs(): array
    {
        $user = OfficeScope::user();

        return collect($this->types())->map(fn (string $model, string $key): array => [
            'label' => ['weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'annual' => __('Annual')][$key],
            'count' => $user === null ? 0 : OfficeOverview::reviewQueueQuery($model, $user)->count(),
        ])->all();
    }

    public function updatedType(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->queueQuery())
            ->columns([
                TextColumn::make('department.name')
                    ->label(__('Office'))
                    ->wrap()
                    ->description(fn (Model $record): ?HtmlString => ReportTableParts::phoneDetail($record->periodLabel()))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')->label(__('Period'))->state(fn (Model $record): string => $record->periodLabel())->visibleFrom('md'),
                TextColumn::make('preparedBy.name')->label(__('Prepared by'))->visibleFrom('lg'),
                TextColumn::make('submittedBy.name')->label(__('Submitted by'))->visibleFrom('xl'),
                TextColumn::make('submitted_at')->label(__('Waiting since'))->since()->dateTimeTooltip()->sortable(),
            ])
            ->defaultSort('submitted_at')
            ->filters([
                SelectFilter::make('department_id')
                    ->label(__('Office'))
                    ->options(fn (): array => OfficeScope::visibleOfficeOptions()),
            ])
            ->recordUrl(fn (Model $record): string => $this->resourceFor()::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('review')
                    ->label(__('Review'))
                    ->button()
                    ->url(fn (Model $record): string => $this->resourceFor()::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading(__('Nothing waiting for review'))
            ->emptyStateDescription(__('New submissions from your subordinate offices will appear here.'));
    }

    private function queueQuery(): Builder
    {
        $user = OfficeScope::user();
        $model = $this->types()[$this->type] ?? WeeklyProgressReport::class;

        if ($user === null) {
            return $model::query()->whereRaw('1 = 0');
        }

        return OfficeOverview::reviewQueueQuery($model, $user)
            ->with(['department', 'preparedBy', 'submittedBy'])
            ->where('status', ReportStatus::Submitted->value);
    }

    /**
     * @return class-string
     */
    private function resourceFor(): string
    {
        return match ($this->type) {
            'monthly' => MonthlyReportResource::class,
            'annual' => AnnualReportResource::class,
            default => WeeklyProgressReportResource::class,
        };
    }

    /**
     * @return array<string, class-string<WeeklyProgressReport|MonthlyReport|AnnualReport>>
     */
    private function types(): array
    {
        return [
            'weekly' => WeeklyProgressReport::class,
            'monthly' => MonthlyReport::class,
            'annual' => AnnualReport::class,
        ];
    }
}
