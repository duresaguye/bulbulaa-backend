<?php

namespace App\Services;

use App\Models\AnnualTarget;
use App\Models\Department;
use App\Models\MonthlyPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rules for who may assign, distribute, allocate, and revise targets, enforced on every change.
 */
class TargetAssignment
{
    /**
     * Organisation-wide planners edit any target; a supervising office edits the targets it distributed.
     */
    public function canEdit(User $user, AnnualTarget $target): bool
    {
        if ($user->hasOrganizationWideScope()) {
            return true;
        }

        return $target->parent_id !== null
            && $user->hasPermission('targets.distribute')
            && $user->belongsToDepartment((int) $target->parent->department_id);
    }

    public function canDistribute(User $user, AnnualTarget $target): bool
    {
        if (! $target->is_active || $this->distributionOptions($user, $target) === []) {
            return false;
        }

        return $user->hasOrganizationWideScope()
            || ($user->hasPermission('targets.distribute') && $user->belongsToDepartment((int) $target->department_id));
    }

    /**
     * The office responsible for a target splits it across months; organisation-wide planners do not.
     */
    public function canAllocate(User $user, AnnualTarget $target): bool
    {
        return $user->hasPermission('reports.manage') && $user->belongsToDepartment((int) $target->department_id);
    }

    /**
     * Direct subordinate offices that do not yet hold a share of this target.
     *
     * @return array<int, string>
     */
    public function distributionOptions(User $user, AnnualTarget $target): array
    {
        $alreadyAssigned = $target->children()->pluck('department_id')->all();

        return Department::query()
            ->where('parent_id', $target->department_id)
            ->where('is_active', true)
            ->whereIn('id', $user->distributableDepartmentIds())
            ->whereNotIn('id', $alreadyAssigned)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  list<array{department_id: int|string, target_value: float|string}>  $shares
     */
    public function distribute(User $user, AnnualTarget $target, array $shares): void
    {
        if (! $this->canDistribute($user, $target)) {
            throw new AuthorizationException('You are not allowed to distribute this target.');
        }

        $allowed = $this->distributionOptions($user, $target);

        DB::transaction(function () use ($user, $target, $shares, $allowed): void {
            foreach ($shares as $share) {
                $departmentId = (int) $share['department_id'];

                if (! array_key_exists($departmentId, $allowed)) {
                    throw ValidationException::withMessages([
                        'department_id' => 'Targets can only be distributed to subordinate offices that do not already hold a share.',
                    ]);
                }

                AnnualTarget::create([
                    'parent_id' => $target->id,
                    'department_id' => $departmentId,
                    'assigned_by' => $user->id,
                    'fiscal_year' => $target->fiscal_year,
                    'title' => $target->title,
                    'description' => $target->description,
                    'measurement_unit' => $target->measurement_unit,
                    'aggregation' => $target->aggregation,
                    'target_value' => $share['target_value'],
                    'is_active' => true,
                ]);
            }
        });
    }

    public function revise(User $user, AnnualTarget $target, float $newValue, string $reason): void
    {
        if (! $this->canEdit($user, $target)) {
            throw new AuthorizationException('You are not allowed to revise this target.');
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'revision_reason' => 'Give the reason for revising the assigned target.',
            ]);
        }

        DB::transaction(function () use ($user, $target, $newValue, $reason): void {
            $target->revisions()->create([
                'revised_by' => $user->id,
                'previous_value' => $target->getOriginal('target_value'),
                'new_value' => $newValue,
                'reason' => $reason,
            ]);

            $target->target_value = $newValue;
            $target->revisionRecorded = true;
            $target->save();
            $target->revisionRecorded = false;
        });
    }

    /**
     * Save the monthly split of an annual target. Months left blank are removed unless progress was already reported.
     *
     * @param  array<int, array{planned_value: float|string|null, planned_activities: string|null}>  $months
     */
    public function allocate(User $user, AnnualTarget $target, array $months): void
    {
        if (! $this->canAllocate($user, $target)) {
            throw new AuthorizationException('Only the office responsible for this target can allocate it across months.');
        }

        DB::transaction(function () use ($target, $months): void {
            $existing = $target->monthlyPlans()->get()->keyBy('ethiopian_month');

            foreach (MonthlyPlan::ETHIOPIAN_MONTHS as $month => $name) {
                $value = $months[$month]['planned_value'] ?? null;
                $plan = $existing->get($month);

                if (blank($value)) {
                    if ($plan === null) {
                        continue;
                    }

                    if ($plan->weeklyProgressEntries()->exists() || $plan->monthlyReportLines()->exists()) {
                        throw ValidationException::withMessages([
                            "months.{$month}.planned_value" => "{$name} already has reported progress, so its target cannot be removed.",
                        ]);
                    }

                    $plan->delete();

                    continue;
                }

                MonthlyPlan::updateOrCreate(
                    ['annual_target_id' => $target->id, 'ethiopian_month' => $month],
                    ['planned_value' => $value, 'planned_activities' => $months[$month]['planned_activities'] ?? null],
                );
            }
        });

        $target->unsetRelation('monthlyPlans');
    }
}
