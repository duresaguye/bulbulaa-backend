<?php

namespace App\Filament\Support;

use App\Support\WebsiteDefaults;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Fills an empty list with the text the website shows today, so staff can edit it instead of retyping it.
 * Only offered while the list is empty. Items are added hidden, ready to check before showing them.
 */
class ImportWebsiteTextAction
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function make(string $model, string $defaultsKey): Action
    {
        return Action::make('importWebsiteText')
            ->label(__('Start from the current website text'))
            ->icon(Heroicon::OutlinedArrowDownOnSquareStack)
            ->color('gray')
            ->visible(fn (): bool => ! $model::query()->exists())
            ->requiresConfirmation()
            ->modalHeading(__('Copy the current website text here?'))
            ->modalDescription(__('The items the website shows today are added here as hidden drafts. Check each one, then switch on "Show on the website".'))
            ->action(function () use ($model, $defaultsKey): void {
                foreach (WebsiteDefaults::section($defaultsKey) as $order => $item) {
                    $model::create([...$item, 'sort_order' => $order + 1, 'is_published' => false]);
                }

                Notification::make()->success()->title(__('Copied'))->send();
            });
    }
}
