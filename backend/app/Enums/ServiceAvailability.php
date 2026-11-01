<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether people can use a city service yet.
 */
enum ServiceAvailability: string implements HasColor, HasLabel
{
    case Available = 'available';
    case InPreparation = 'in_preparation';
    case ComingSoon = 'coming_soon';

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
            self::Available => 'Available now',
            self::InPreparation => 'In preparation',
            self::ComingSoon => 'Coming soon',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::InPreparation => 'warning',
            self::ComingSoon => 'gray',
        };
    }
}
