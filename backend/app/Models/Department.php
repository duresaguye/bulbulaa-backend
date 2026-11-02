<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

#[Fillable(['parent_id', 'name', 'code', 'description', 'is_active', 'is_public', 'public_name', 'public_description', 'public_location', 'public_phone', 'public_email'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, HasTranslations, RecordsAudit;

    protected static function booted(): void
    {
        static::saving(function (self $department): void {
            if ($department->parent_id !== null && $department->exists
                && in_array((int) $department->parent_id, [$department->id, ...self::descendantIdsOf([$department->id])], true)) {
                throw ValidationException::withMessages([
                    'parent_id' => __('An office cannot report to itself or to one of its own subordinate offices.'),
                ]);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_primary')->withTimestamps();
    }

    public function annualTargets(): HasMany
    {
        return $this->hasMany(AnnualTarget::class);
    }

    public function weeklyProgressReports(): HasMany
    {
        return $this->hasMany(WeeklyProgressReport::class);
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class);
    }

    public function annualReports(): HasMany
    {
        return $this->hasMany(AnnualReport::class);
    }

    /**
     * IDs of every office below the given offices in the hierarchy, excluding the offices themselves.
     *
     * @param  iterable<int>  $departmentIds
     * @return list<int>
     */
    public static function descendantIdsOf(iterable $departmentIds): array
    {
        $childrenByParent = self::query()
            ->whereNotNull('parent_id')
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id')
            ->map(fn (Collection $children) => $children->pluck('id')->all());

        $descendants = [];
        $queue = collect($departmentIds)->map(fn ($id) => (int) $id)->all();

        while ($queue !== []) {
            $parentId = array_shift($queue);

            foreach ($childrenByParent->get($parentId, []) as $childId) {
                if (! in_array($childId, $descendants, true)) {
                    $descendants[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $descendants;
    }

    /**
     * Offices in hierarchy order with their depth, for indented lists.
     *
     * @param  list<int>|null  $onlyIds
     * @return Collection<int, array{department: self, depth: int}>
     */
    public static function tree(?array $onlyIds = null): Collection
    {
        $departments = self::query()->orderBy('name')->get();
        $visible = $onlyIds === null ? $departments->pluck('id')->all() : $onlyIds;
        $byParent = $departments->groupBy(fn (self $department) => $department->parent_id ?? 0);
        $rows = collect();

        $walk = function (int $parentId, int $depth) use (&$walk, $byParent, $visible, $rows): void {
            foreach ($byParent->get($parentId, []) as $department) {
                $isVisible = in_array($department->id, $visible, true);

                if ($isVisible) {
                    $rows->push(['department' => $department, 'depth' => $depth]);
                }

                $walk($department->id, $isVisible ? $depth + 1 : $depth);
            }
        };

        $walk(0, 0);

        return $rows;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'public_name' => 'array',
            'public_description' => 'array',
            'public_location' => 'array',
        ];
    }
}
