<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * How an indicator's values combine across weeks, months, and offices.
 */
enum Aggregation: string implements HasDescription, HasLabel
{
    case Sum = 'sum';
    case Average = 'average';
    case Latest = 'latest';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sum => 'Count or total',
            self::Average => 'Average rate',
            self::Latest => 'Current level',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Sum => 'Results add up across periods, for example requests processed or kilometres built. Monthly targets should total the annual target.',
            self::Average => 'Each period has its own rate, for example a satisfaction percentage. The year is the average of reported periods.',
            self::Latest => 'The most recent value is the result, for example coverage reached so far. The year is the latest reported value.',
        };
    }

    public function isAdditive(): bool
    {
        return $this === self::Sum;
    }

    /**
     * Combine period values in reporting order.
     *
     * @param  list<float>  $values
     */
    public function combine(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return match ($this) {
            self::Sum => array_sum($values),
            self::Average => array_sum($values) / count($values),
            self::Latest => $values[array_key_last($values)],
        };
    }
}
