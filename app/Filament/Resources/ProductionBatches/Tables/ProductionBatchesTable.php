<?php

namespace App\Filament\Resources\ProductionBatches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductionBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_code')
                    ->label('Batch Code (MFD)')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('production_date')
                    ->label('Production Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('product.product_code')
                    ->label('Product Code')
                    ->searchable(),

                TextColumn::make('product.name')
                    ->label('Product Name')
                    ->searchable(),

                TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('qty_produced')
                    ->label('Qty Produced')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->suffix(' cartons'),

                TextColumn::make('remaining_stock')
                    ->label('Remaining Stock')
                    ->getStateUsing(function ($record) {
                        return $record->getRemainingStock();
                    })
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' cartons')
                    ->color(fn($state) => $state > 0 ? 'success' : ($state == 0 ? 'warning' : 'danger')),

                TextColumn::make('total_shipped')
                    ->label('Total Shipped')
                    ->getStateUsing(function ($record) {
                        return $record->getTotalShipped();
                    })
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' cartons')
                    ->toggleable(isToggledHiddenByDefault: true),

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

                SelectFilter::make('has_remaining_stock')
                    ->label('Stock Status')
                    ->options([
                        'available' => 'Has Remaining Stock',
                        'depleted' => 'Fully Shipped',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'available') {
                            return $query->whereHas('stockMovements', function ($q) {
                                $q->where('item_type', 'finished_goods')
                                    ->where('movement_type', 'in');
                            })->whereDoesntHave('shipmentItems', function ($q) {
                                $q->whereRaw('qty_shipped >= (SELECT qty_produced FROM production_batches WHERE id = production_batch_id)');
                            });
                        } elseif ($data['value'] === 'depleted') {
                            return $query->whereHas('shipmentItems', function ($q) {
                                $q->whereRaw('qty_shipped >= (SELECT qty_produced FROM production_batches WHERE id = production_batch_id)');
                            });
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('production_date', 'desc');
    }
}
