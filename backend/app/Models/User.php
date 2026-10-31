<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RecordsAudit;

    /**
     * @var array<string, bool>
     */
    private array $permissionCache = [];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withPivot('is_primary')->withTimestamps();
    }

    public function submittedWeeklyProgressReports(): HasMany
    {
        return $this->hasMany(WeeklyProgressReport::class, 'submitted_by');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissionCache[$permission] ??= $this->roles()
            ->where(fn ($query) => $query
                ->where('name', 'system_admin')
                ->orWhereHas('permissions', fn ($query) => $query->where('name', $permission)))
            ->exists();
    }

    /**
     * Organisation-wide oversight belongs to whoever may set annual targets for every office.
     */
    public function hasOrganizationWideScope(): bool
    {
        return $this->hasPermission('reports.targets.manage');
    }

    /**
     * Offices the user belongs to directly.
     *
     * @return list<int>
     */
    public function memberDepartmentIds(): array
    {
        return $this->departments()->pluck('departments.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Offices whose plans and reports the user may see: their own offices and everything below them.
     *
     * @return list<int>
     */
    public function visibleDepartmentIds(): array
    {
        if ($this->hasOrganizationWideScope()) {
            return Department::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $memberIds = $this->memberDepartmentIds();

        return array_values(array_unique([...$memberIds, ...Department::descendantIdsOf($memberIds)]));
    }

    /**
     * Offices whose reports the user may review: offices below theirs, or every office for organisation-wide reviewers.
     *
     * @return list<int>
     */
    public function reviewableDepartmentIds(): array
    {
        if (! $this->hasPermission('reports.review')) {
            return [];
        }

        if ($this->hasOrganizationWideScope()) {
            return $this->visibleDepartmentIds();
        }

        return Department::descendantIdsOf($this->memberDepartmentIds());
    }

    /**
     * Offices the user may distribute targets to: offices below their own.
     *
     * @return list<int>
     */
    public function distributableDepartmentIds(): array
    {
        if ($this->hasOrganizationWideScope()) {
            return $this->visibleDepartmentIds();
        }

        if (! $this->hasPermission('targets.distribute')) {
            return [];
        }

        return Department::descendantIdsOf($this->memberDepartmentIds());
    }

    public function canViewDepartment(int $departmentId): bool
    {
        return in_array($departmentId, $this->visibleDepartmentIds(), true);
    }

    public function belongsToDepartment(int $departmentId): bool
    {
        return in_array($departmentId, $this->memberDepartmentIds(), true);
    }

    public function primaryDepartment(): ?Department
    {
        return $this->departments()->orderByDesc('department_user.is_primary')->orderBy('name')->first();
    }

    /**
     * Whether the user may approve or return the given report. Nobody reviews a report they prepared or submitted.
     */
    public function canReview(Model $report): bool
    {
        return in_array((int) $report->department_id, $this->reviewableDepartmentIds(), true)
            && (int) $report->prepared_by !== $this->id
            && (int) $report->submitted_by !== $this->id;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->roles()->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
