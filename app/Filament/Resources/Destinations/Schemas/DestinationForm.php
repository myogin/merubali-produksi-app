<?php

namespace App\Filament\Resources\Destinations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DestinationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Destination Information')
                    ->description('Basic information about the destination')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Destination Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Jakarta, Indonesia')
                                    ->columnSpan(1),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Inactive destinations will not appear in dropdowns')
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
