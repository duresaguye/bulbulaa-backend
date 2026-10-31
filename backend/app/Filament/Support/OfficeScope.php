<?php

namespace App\Filament\Support;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts Filament queries and select options to the offices the signed-in user is allowed to see or act for.
 */
class OfficeScope
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function applyVisible(Builder $query, string $column = 'department_id'): Builder
    {
        $user = self::user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasOrganizationWideScope()) {
            return $query;
        }

        return $query->whereIn($column, $user->visibleDepartmentIds());
    }

    /**
     * Offices the user can write reports for: their own offices, or every active office for organisation-wide staff.
     *
     * @return array<int, string>
     */
    public static function reportingOfficeOptions(): array
    {
        $user = self::user();
        $query = Department::query()->where('is_active', true)->orderBy('name');

        if ($user !== null && ! $user->hasOrganizationWideScope()) {
            $query->whereIn('id', $user->memberDepartmentIds());
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function visibleOfficeOptions(): array
    {
        $user = self::user();

        if ($user === null) {
            return [];
        }

        return Department::query()
            ->whereIn('id', $user->visibleDepartmentIds())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function canWriteFor(int $departmentId): bool
    {
        $user = self::user();

        return $user !== null
            && $user->hasPermission('reports.manage')
            && ($user->hasOrganizationWideScope() || $user->belongsToDepartment($departmentId));
    }

    public static function defaultOfficeId(): ?int
    {
        $options = self::reportingOfficeOptions();
        $primary = self::user()?->primaryDepartment();

        if ($primary !== null && array_key_exists($primary->id, $options)) {
            return $primary->id;
        }

        return count($options) === 1 ? (int) array_key_first($options) : null;
    }
}
