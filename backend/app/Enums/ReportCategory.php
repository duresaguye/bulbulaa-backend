<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a member of the public is sending through the website.
 */
enum ReportCategory: string implements HasColor, HasLabel
{
    case Message = 'message';
    case Complaint = 'complaint';
    case Safety = 'safety';
    case Suggestion = 'suggestion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Message => __('Question or message'),
            self::Complaint => __('Complaint about a service'),
            self::Safety => __('Safety concern'),
            self::Suggestion => __('Suggestion'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Message => 'primary',
            self::Complaint => 'warning',
            self::Safety => 'danger',
            self::Suggestion => 'info',
        };
    }
}
