<?php

namespace App\Filament\Resources\Boms\Schemas;

use App\Models\PackagingItem;
use App\Models\Product;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bill of Materials')
                    ->description('Define packaging requirements for each product')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->required()
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(fn(Product $record): string => "{$record->product_code} - {$record->name}")
                                    ->helperText('Select the finished product'),

                                Select::make('packaging_item_id')
                                    ->label('Packaging Item')
                                    ->required()
                                    ->relationship('packagingItem', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(fn(PackagingItem $record): string => "{$record->packaging_code} - {$record->name}")
                                    ->helperText('Select the packaging material required'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('qty_per_unit')
                                    ->label('Quantity per Unit')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->placeholder('e.g., 50, 24, 1')
                                    ->helperText('How many packaging items needed per product unit'),

                                Select::make('uom')
                                    ->label('Unit of Measure')
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
                                    ->helperText('Unit of measure for the quantity'),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
