<?php

namespace App\Filament\Resources\StockMovements\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('item_type')
                    ->label('Item Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'packaging' => 'info',
                        'finished_goods' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'packaging' => 'Packaging',
                        'finished_goods' => 'Finished Goods',
                        default => $state,
                    }),

                TextColumn::make('item_name')
                    ->label('Item')
                    ->getStateUsing(function ($record) {
                        if ($record->item_type === 'packaging') {
                            return $record->packagingItem?->name ?? 'Unknown Packaging';
                        } elseif ($record->item_type === 'finished_goods') {
                            return $record->product?->name ?? 'Unknown Product';
                        }
                        return 'Unknown';
                    })
                    ->searchable(['packaging_items.name', 'products.name']),

                TextColumn::make('item_code')
                    ->label('Item Code')
                    ->getStateUsing(function ($record) {
                        if ($record->item_type === 'packaging') {
                            return $record->packagingItem?->packaging_code ?? 'N/A';
                        } elseif ($record->item_type === 'finished_goods') {
                            return $record->product?->product_code ?? 'N/A';
                        }
                        return 'N/A';
                    })
                    ->searchable(['packaging_items.packaging_code', 'products.product_code']),

                TextColumn::make('productionBatch.batch_code')
                    ->label('Batch Code')
                    ->placeholder('N/A')
                    ->searchable(),

                TextColumn::make('movement_type')
                    ->label('Movement')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'in' => 'IN (+)',
                        'out' => 'OUT (-)',
                        default => $state,
                    }),

                TextColumn::make('qty')
                    ->label('Quantity')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->color(
                        fn(string $state, $record): string =>
                        $record->movement_type === 'in' ? 'success' : 'danger'
                    ),

                TextColumn::make('uom')
                    ->label('Unit')
                    ->searchable(),

                TextColumn::make('reference_type')
                    ->label('Reference Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'receipt' => 'info',
                        'production' => 'warning',
                        'shipment' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'receipt' => 'Receipt',
                        'production' => 'Production',
                        'shipment' => 'Shipment',
                        default => $state,
                    }),

                TextColumn::make('reference_number')
                    ->label('Reference #')
                    ->getStateUsing(function ($record) {
                        switch ($record->reference_type) {
                            case 'receipt':
                                return $record->receipt?->receipt_number ?? 'N/A';
                            case 'production':
                                return $record->productionBatchReference?->batch_code ?? 'N/A';
                            case 'shipment':
                                return $record->shipment?->shipment_number ?? 'N/A';
                            default:
                                return 'N/A';
                        }
                    })
                    ->searchable(['receipts.receipt_number', 'production_batches.batch_code', 'shipments.shipment_number']),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('item_type')
                    ->label('Item Type')
                    ->options([
                        'packaging' => 'Packaging',
                        'finished_goods' => 'Finished Goods',
                    ]),

                SelectFilter::make('movement_type')
                    ->label('Movement Type')
                    ->options([
                        'in' => 'Inbound (+)',
                        'out' => 'Outbound (-)',
                    ]),

                SelectFilter::make('reference_type')
                    ->label('Reference Type')
                    ->options([
                        'receipt' => 'Receipt',
                        'production' => 'Production',
                        'shipment' => 'Shipment',
                    ]),

                Filter::make('movement_date')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('movement_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('movement_date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                // No actions - read-only
            ])
            ->toolbarActions([
                // No bulk actions - read-only
            ])
            ->defaultSort('movement_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
