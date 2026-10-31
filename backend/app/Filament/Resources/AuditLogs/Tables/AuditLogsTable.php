<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Filament\Support\ReportTableParts;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When'))
                    ->dateTime('j M Y, H:i')
                    ->description(fn ($record): ?HtmlString => ReportTableParts::phoneDetail($record->user?->name ?? __('System')))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('By'))
                    ->placeholder(__('System'))
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('auditable_type')
                    ->label(__('Record'))
                    ->formatStateUsing(fn (?string $state, $record): string => Str::headline(class_basename((string) $state)).' #'.$record->auditable_id)
                    ->searchable()
                    ->visibleFrom('sm'),
                TextColumn::make('new_values')
                    ->label(__('New values'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state) : (string) $state)
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('old_values')
                    ->label(__('Previous values'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state) : (string) $state)
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')
                    ->label(__('IP address'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->options([
                    'created' => __('Created'),
                    'updated' => __('Updated'),
                    'deleted' => __('Deleted'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
