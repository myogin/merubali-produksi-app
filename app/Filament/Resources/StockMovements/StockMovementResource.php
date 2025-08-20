<?php

namespace App\Filament\Resources\StockMovements;

use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Stock Movements';

    protected static ?string $modelLabel = 'Stock Movement';

    protected static ?string $pluralModelLabel = 'Stock Movements';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false; // Stock movements are auto-generated only
    }

    public static function canEdit($record): bool
    {
        return false; // Stock movements are read-only
    }

    public static function canDelete($record): bool
    {
        return false; // Stock movements should not be deleted
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
