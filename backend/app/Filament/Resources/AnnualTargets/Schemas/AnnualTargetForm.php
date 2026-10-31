<?php

namespace App\Filament\Resources\AnnualTargets\Schemas;

use App\Enums\Aggregation;
use App\Models\AnnualTarget;
use App\Models\Department;
use App\Services\EthiopianCalendar;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AnnualTargetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Assignment'))
                ->description(__('Which office is responsible for this target, and for which Ethiopian calendar year.'))
                ->columns(3)
                ->schema([
                    Select::make('department_id')
                        ->label(__('Responsible office'))
                        ->options(fn (): array => Department::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->required()
                        ->searchable()
                        ->disabled(fn (?AnnualTarget $record): bool => $record !== null && ($record->parent_id !== null || $record->hasReportedProgress()))
                        ->columnSpan(2),
                    TextInput::make('fiscal_year')
                        ->label(__('Year (E.C.)'))
                        ->numeric()
                        ->minValue(1900)
                        ->maxValue(2200)
                        ->default(fn (): int => EthiopianCalendar::today()['year'])
                        ->disabled(fn (?AnnualTarget $record): bool => $record !== null && ($record->parent_id !== null || $record->hasReportedProgress()))
                        ->required(),
                    TextInput::make('title')
                        ->label(__('Objective or target'))
                        ->placeholder(__('For example: Process service requests'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label(__('Expected outcome'))
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make(__('Measurement'))
                ->columns(2)
                ->schema([
                    TextInput::make('measurement_unit')
                        ->label(__('Measurement unit'))
                        ->placeholder(__('For example: requests, households, kilometres, percent'))
                        ->required()
                        ->maxLength(64),
                    TextInput::make('target_value')
                        ->label(__('Annual target'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->live(onBlur: true)
                        ->helperText(fn (?AnnualTarget $record): ?string => $record === null
                            ? null
                            : __('Originally assigned: :value. Changing it records a revision.', ['value' => $record->formatValue($record->original_target_value)])),
                    Radio::make('aggregation')
                        ->label(__('How results combine over the year'))
                        ->options(Aggregation::class)
                        ->default(Aggregation::Sum->value)
                        ->required()
                        ->disabled(fn (?AnnualTarget $record): bool => $record !== null && ($record->parent_id !== null || $record->hasReportedProgress()))
                        ->columnSpanFull(),
                    Textarea::make('revision_reason')
                        ->label(__('Reason for revising the target'))
                        ->helperText(__('Required when the annual target value changes. The reason is kept in the revision history.'))
                        ->rows(2)
                        ->visible(fn (?AnnualTarget $record): bool => $record !== null)
                        ->required(fn (Get $get, ?AnnualTarget $record): bool => $record !== null
                            && is_numeric($get('target_value'))
                            && (float) $get('target_value') !== (float) $record->target_value)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label(__('Active'))
                        ->helperText(__('Inactive targets are hidden from reporting forms but keep their history.'))
                        ->default(true),
                ]),
        ]);
    }
}
