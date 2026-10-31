<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['annual_target_id', 'revised_by', 'previous_value', 'new_value', 'reason'])]
class AnnualTargetRevision extends Model
{
    use RecordsAudit;

    public function annualTarget(): BelongsTo
    {
        return $this->belongsTo(AnnualTarget::class);
    }

    public function revisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }

    protected function casts(): array
    {
        return [
            'previous_value' => 'decimal:2',
            'new_value' => 'decimal:2',
        ];
    }
}
