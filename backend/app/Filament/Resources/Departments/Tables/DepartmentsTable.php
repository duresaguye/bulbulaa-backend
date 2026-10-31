<?php

namespace App\Filament\Resources\Departments\Tables;

use App\Filament\Support\ReportTableParts;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->description(fn ($record): ?HtmlString => ReportTableParts::phoneDetail($record->parent ? 'Reports to '.$record->parent->name : __('Top level')))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->visibleFrom('sm'),
                TextColumn::make('parent.name')
                    ->label(__('Reports to'))
                    ->placeholder(__('Top level'))
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('children_count')
                    ->label(__('Subordinate offices'))
                    ->counts('children')
                    ->alignEnd()
                    ->visibleFrom('lg'),
                TextColumn::make('users_count')
                    ->label(__('Staff'))
                    ->counts('users')
                    ->alignEnd()
                    ->visibleFrom('lg'),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->visibleFrom('sm'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('parent_id')
                    ->label(__('Reports to'))
                    ->relationship('parent', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
