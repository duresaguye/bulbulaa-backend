<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a city project stands, as shown on the website.
 */
enum ProjectStatus: string implements HasColor, HasLabel
{
    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Completed = 'completed';

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
            self::Planned => 'Planned',
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Ongoing => 'warning',
            self::Completed => 'success',
        };
    }
}
