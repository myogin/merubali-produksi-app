<?php

namespace App\Filament\Resources\Boms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.product_code')
                    ->label('Product Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('product.name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('packagingItem.packaging_code')
                    ->label('Packaging Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('packagingItem.name')
                    ->label('Packaging Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('qty_per_unit')
                    ->label('Quantity')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),

                TextColumn::make('uom')
                    ->label('Unit')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('total_requirement')
                    ->label('Total Requirement')
                    ->getStateUsing(fn($record) => $record->qty_per_unit . ' ' . $record->uom . ' per unit')
                    ->wrap(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('packaging_item_id')
                    ->label('Packaging Item')
                    ->relationship('packagingItem', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('uom')
                    ->label('Unit of Measure')
                    ->options([
                        'pcs' => 'Pieces (pcs)',
                        'kg' => 'Kilogram (kg)',
                        'g' => 'Gram (g)',
                        'ltr' => 'Liter (ltr)',
                        'ml' => 'Milliliter (ml)',
                        'box' => 'Box',
                        'pack' => 'Pack',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                'product.name',
                'packagingItem.name',
            ]);
    }
}
