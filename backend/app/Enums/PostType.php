<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The kinds of post the city publishes on its website.
 */
enum PostType: string implements HasColor, HasLabel
{
    case News = 'news';
    case Announcement = 'announcement';
    case Vacancy = 'vacancy';
    case Tender = 'tender';

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
            self::News => 'News',
            self::Announcement => 'Announcement',
            self::Vacancy => 'Vacancy',
            self::Tender => 'Tender',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::News => 'primary',
            self::Announcement => 'warning',
            self::Vacancy => 'info',
            self::Tender => 'gray',
        };
    }

    /**
     * Vacancies and tenders close on a date; announcements may have one.
     */
    public function usesDeadline(): bool
    {
        return $this !== self::News;
    }
}
