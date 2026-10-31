<?php

namespace App\Models;

use App\Enums\CitizenReportStatus;
use App\Enums\ReportCategory;
use App\Models\Concerns\RecordsAudit;
use Database\Factories\CitizenReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Something a member of the public sent through the website. Photos are kept on the private disk.
 */
#[Fillable(['category', 'details', 'location', 'is_anonymous', 'reporter_name', 'reporter_contact', 'photos', 'locale', 'status', 'department_id', 'staff_note', 'handled_by'])]
class CitizenReport extends Model
{
    /** @use HasFactory<CitizenReportFactory> */
    use HasFactory;

    use RecordsAudit;

    public const PHOTO_DISK = 'local';

    protected static function booted(): void
    {
        static::creating(function (CitizenReport $report): void {
            $report->reference ??= self::nextReference();
            $report->status ??= CitizenReportStatus::New;

            if ($report->is_anonymous) {
                $report->reporter_name = null;
                $report->reporter_contact = null;
            }
        });

        static::deleted(function (CitizenReport $report): void {
            Storage::disk(self::PHOTO_DISK)->delete($report->photos ?? []);
        });
    }

    /**
     * A short number people can quote when they follow up, such as "BUL-2026-0042".
     */
    public static function nextReference(): string
    {
        $year = now()->year;

        return DB::transaction(function () use ($year): string {
            $count = self::query()->whereYear('created_at', $year)->lockForUpdate()->count() + 1;

            do {
                $reference = sprintf('BUL-%d-%04d', $year, $count++);
            } while (self::query()->where('reference', $reference)->exists());

            return $reference;
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    protected function casts(): array
    {
        return [
            'category' => ReportCategory::class,
            'status' => CitizenReportStatus::class,
            'is_anonymous' => 'boolean',
            'photos' => 'array',
        ];
    }
}
