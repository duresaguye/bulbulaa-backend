<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReviewAction: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => __('Submitted'),
            self::Returned => __('Returned for correction'),
            self::Approved => __('Approved'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Returned => 'danger',
            self::Approved => 'success',
        };
    }
}
