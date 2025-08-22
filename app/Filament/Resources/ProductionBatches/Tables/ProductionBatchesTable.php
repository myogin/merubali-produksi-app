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
                TextColumn::make('production_date')
                    ->label('Production Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('po_number')
                    ->label('PO Number')
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('productionBatchItems_count')
                    ->label('Items Count')
                    ->counts('productionBatchItems')
                    ->suffix(' items'),

                TextColumn::make('total_produced')
                    ->label('Total Produced')
                    ->getStateUsing(function ($record) {
                        return $record->getTotalProduced();
                    })
                    ->numeric()
                    ->suffix(' cartons'),

                TextColumn::make('total_shipped')
                    ->label('Total Shipped')
                    ->getStateUsing(function ($record) {
                        return $record->getTotalShipped();
                    })
                    ->numeric()
                    ->suffix(' cartons'),

                TextColumn::make('remaining_stock')
                    ->label('Remaining Stock')
                    ->getStateUsing(function ($record) {
                        return $record->getRemainingStock();
                    })
                    ->numeric()
                    ->suffix(' cartons')
                    ->color(fn($state) => $state > 0 ? 'success' : ($state == 0 ? 'warning' : 'danger')),

                TextColumn::make('batch_codes')
                    ->label('Batch Codes')
                    ->getStateUsing(function ($record) {
                        return $record->productionBatchItems->pluck('batch_code')->join(', ');
                    })
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->productionBatchItems->pluck('batch_code')->join(', ');
                    }),

                TextColumn::make('products')
                    ->label('Products')
                    ->getStateUsing(function ($record) {
                        return $record->productionBatchItems->load('product')->pluck('product.name')->unique()->join(', ');
                    })
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->productionBatchItems->load('product')->pluck('product.name')->unique()->join(', ');
                    }),

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
                SelectFilter::make('has_remaining_stock')
                    ->label('Stock Status')
                    ->options([
                        'available' => 'Has Remaining Stock',
                        'depleted' => 'Fully Shipped',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'available') {
                            return $query->whereHas('productionBatchItems', function ($q) {
                                $q->whereHas('stockMovements', function ($sq) {
                                    $sq->where('item_type', 'finished_goods')
                                        ->where('movement_type', 'in');
                                })->whereDoesntHave('shipmentItems', function ($sq) {
                                    $sq->whereRaw('qty_shipped >= (SELECT qty_produced FROM production_batch_items WHERE id = production_batch_item_id)');
                                });
                            });
                        } elseif ($data['value'] === 'depleted') {
                            return $query->whereHas('productionBatchItems', function ($q) {
                                $q->whereHas('shipmentItems', function ($sq) {
                                    $sq->whereRaw('qty_shipped >= (SELECT qty_produced FROM production_batch_items WHERE id = production_batch_item_id)');
                                });
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
