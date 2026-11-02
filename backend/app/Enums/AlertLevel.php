<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How important a website alert is. Urgent alerts are for safety and service interruptions.
 */
enum AlertLevel: string implements HasColor, HasLabel
{
    case Info = 'info';
    case Warning = 'warning';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return __($this->englishLabel());
    }

    /**
     * The label in each website language, since the website switches language in the browser.
     *
     * @return array{om: string, am: string, en: string}
     */
    public function labels(): array
    {
        return [
            'om' => __($this->englishLabel(), locale: 'om'),
            'am' => __($this->englishLabel(), locale: 'am'),
            'en' => $this->englishLabel(),
        ];
    }

    private function englishLabel(): string
    {
        return match ($this) {
            self::Info => 'Information',
            self::Warning => 'Warning',
            self::Urgent => 'Urgent',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Urgent => 'danger',
        };
    }
}
