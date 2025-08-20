<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->description('Basic information about the finished product')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('product_code')
                                    ->label('Product Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('e.g., CCO-CTN50')
                                    ->helperText('Unique code for the finished product'),

                                TextInput::make('name')
                                    ->label('Product Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., STP-CCO-50')
                                    ->helperText('Display name for the product'),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Optional description of the product'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive products will not be available for production'),
                    ])
                    ->columns(1),
            ]);
    }
}
