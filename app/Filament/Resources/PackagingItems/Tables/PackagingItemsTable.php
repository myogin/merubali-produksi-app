<?php

namespace App\Filament\Resources\PackagingItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PackagingItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('packaging_code')
                    ->label('Packaging Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Packaging Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('base_uom')
                    ->label('Unit')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),

                TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->getStateUsing(fn($record) => $record->getCurrentStock())
                    ->formatStateUsing(fn($state, $record) => $state . ' ' . $record->base_uom)
                    ->sortable(false)
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger'),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

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
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ])
                    ->default(1),

                SelectFilter::make('base_uom')
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
            ->defaultSort('created_at', 'desc');
    }
}
