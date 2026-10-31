<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CitizenReportStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::InProgress => __('Being handled'),
            self::Resolved => __('Resolved'),
            self::Closed => __('Closed without action'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }
}
