<?php

namespace App\Filament\Support;

use App\Enums\ReportStatus;
use Filament\Schemas\Components\Callout;
use Illuminate\Database\Eloquent\Model;

class ReportFormParts
{
    /**
     * The reviewer's latest correction request, shown above the form while a report is returned.
     */
    public static function returnedCallout(): Callout
    {
        return Callout::make(__('Returned for correction'))
            ->description(function (?Model $record): string {
                $review = $record?->latestReturn;

                if ($review === null) {
                    return '';
                }

                return trim(($review->user?->name ?? __('The reviewer')).' on '.$review->created_at->format('j M Y').': '.$review->comment);
            })
            ->danger()
            ->visible(fn (?Model $record): bool => $record?->status === ReportStatus::Returned)
            ->columnSpanFull();
    }
}
