<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The kinds of public document the city offers for download.
 */
enum DocumentCategory: string implements HasColor, HasLabel
{
    case Form = 'form';
    case Proclamation = 'proclamation';
    case Report = 'report';
    case Guideline = 'guideline';
    case Other = 'other';

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
            self::Form => 'Form',
            self::Proclamation => 'Proclamation or regulation',
            self::Report => 'Report',
            self::Guideline => 'Guideline',
            self::Other => 'Other document',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Form => 'primary',
            self::Proclamation => 'warning',
            self::Report => 'info',
            self::Guideline => 'success',
            self::Other => 'gray',
        };
    }
}
