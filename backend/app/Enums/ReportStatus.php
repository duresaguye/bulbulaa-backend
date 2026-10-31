<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum ReportStatus: string implements HasColor, HasDescription, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Submitted => __('Awaiting review'),
            self::Returned => __('Returned for correction'),
            self::Approved => __('Approved'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Returned => 'danger',
            self::Approved => 'success',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Draft => __('Saved but not yet submitted. Only your office can see it.'),
            self::Submitted => __('Submitted and waiting for the supervising office to review it.'),
            self::Returned => __('The reviewer asked for corrections. Update the report and submit it again.'),
            self::Approved => __('Approved by the reviewer. The report can no longer be changed.'),
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    /**
     * Statuses whose figures count toward reported progress.
     *
     * @return list<string>
     */
    public static function reportedValues(): array
    {
        return [self::Submitted->value, self::Approved->value];
    }
}
