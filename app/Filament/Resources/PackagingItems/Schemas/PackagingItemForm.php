<?php

namespace App\Filament\Resources\PackagingItems\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PackagingItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Packaging Item Information')
                    ->description('Basic information about the packaging material')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('packaging_code')
                                    ->label('Packaging Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('e.g., STP-CCO-50, CTN50')
                                    ->helperText('Unique code for the packaging item'),

                                TextInput::make('name')
                                    ->label('Packaging Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Standing Pouch 50g, Karton Box 50 pcs')
                                    ->helperText('Display name for the packaging item'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('base_uom')
                                    ->label('Base Unit of Measure')
                                    ->required()
                                    ->options([
                                        'pcs' => 'Pieces (pcs)',
                                        'kg' => 'Kilogram (kg)',
                                        'g' => 'Gram (g)',
                                        'ltr' => 'Liter (ltr)',
                                        'ml' => 'Milliliter (ml)',
                                        'box' => 'Box',
                                        'pack' => 'Pack',
                                    ])
                                    ->default('pcs')
                                    ->helperText('Base unit for stock calculations'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Inactive items will not be available for use'),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Optional description of the packaging item'),
                    ])
                    ->columns(1),
            ]);
    }
}
