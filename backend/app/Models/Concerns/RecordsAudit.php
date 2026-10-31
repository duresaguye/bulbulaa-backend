<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait RecordsAudit
{
    protected static function bootRecordsAudit(): void
    {
        static::created(fn (Model $model) => $model->writeAuditLog('created'));
        static::updated(fn (Model $model) => $model->writeAuditLog('updated'));
        static::deleted(fn (Model $model) => $model->writeAuditLog('deleted'));
    }

    private function writeAuditLog(string $event): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $event === 'created' ? null : $this->auditAttributes($this->getRawOriginal()),
            'new_values' => $event === 'deleted' ? null : $this->auditAttributes($this->getAttributes()),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditAttributes(array $attributes): array
    {
        unset($attributes['password'], $attributes['remember_token']);

        return $attributes;
    }
}
