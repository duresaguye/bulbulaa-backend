<?php

namespace App\Filament\Resources\Departments\Schemas;

use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('code')
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true),
                Select::make('parent_id')
                    ->label('Reports to')
                    ->helperText('The supervising office that distributes targets to this office and reviews its reports. Leave empty for the top of the organisation.')
                    ->options(fn (?Department $record): array => Department::query()
                        ->when($record, fn ($query) => $query->whereNotIn('id', [$record->id, ...Department::descendantIdsOf([$record->id])]))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
