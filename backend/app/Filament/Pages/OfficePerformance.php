<?php

namespace App\Filament\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\ExportActions;
use App\Filament\Support\OfficeScope;
use App\Models\AnnualTarget;
use App\Models\Department;
use App\Services\EthiopianCalendar;
use App\Services\OfficeOverview;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Every office the user can see, in hierarchy order, with target pace and reporting compliance.
 */
class OfficePerformance extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Oversight';

    protected static ?string $navigationLabel = 'Office performance';

    protected static ?string $title = 'Office performance';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.office-performance';

    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $unit = null;

    #[Url]
    public string $status = 'all';

    #[Url]
    public ?int $expanded = null;

    public static function canAccess(): bool
    {
        $user = OfficeScope::user();

        return $user !== null && $user->hasPermission('reports.view')
            && ($user->hasOrganizationWideScope() || Department::descendantIdsOf($user->memberDepartmentIds()) !== []);
    }

    public function mount(): void
    {
        $this->year ??= EthiopianCalendar::today()['year'];
    }

    protected function getHeaderActions(): array
    {
        $query = fn (): array => array_filter(['year' => $this->year, 'unit' => $this->unit, 'status' => $this->status]);

        return [
            ExportActions::group(
                fn (): string => route('filament.admin.exports.office-performance.print', $query()),
                fn (): string => route('filament.admin.exports.office-performance.excel', $query()),
            ),
        ];
    }

    public function getSubheading(): string
    {
        return __('Pace compares each target\'s reported results with its monthly allocation up to the end of last month.');
    }

    /**
     * @return array<int, int>
     */
    public function yearOptions(): array
    {
        return AnnualTarget::query()->distinct()->pluck('fiscal_year')
            ->push(EthiopianCalendar::today()['year'])
            ->unique()->sortDesc()
            ->mapWithKeys(fn ($year) => [(int) $year => (int) $year])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function unitOptions(): array
    {
        return Department::query()
            ->whereIn('id', OfficeScope::user()->visibleDepartmentIds())
            ->whereHas('children')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return [
            'all' => __('All offices'),
            'attention' => __('Behind plan or at risk'),
            'overdue' => __('Overdue reports'),
            'review' => __('Reports awaiting review'),
        ];
    }

    public function toggle(int $departmentId): void
    {
        $this->expanded = $this->expanded === $departmentId ? null : $departmentId;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        $ids = OfficeScope::user()->visibleDepartmentIds();

        if ($this->unit && in_array($this->unit, $ids, true)) {
            $ids = [$this->unit, ...Department::descendantIdsOf([$this->unit])];
        }

        $rows = app(OfficeOverview::class)->rows($ids, (int) $this->year);

        return match ($this->status) {
            'attention' => $rows->filter(fn (array $row) => $row['counts']['behind'] + $row['counts']['at_risk'] > 0)->values(),
            'overdue' => $rows->filter(fn (array $row) => $row['overdue'] > 0)->values(),
            'review' => $rows->filter(fn (array $row) => $row['awaiting_review'] > 0)->values(),
            default => $rows,
        };
    }
}
