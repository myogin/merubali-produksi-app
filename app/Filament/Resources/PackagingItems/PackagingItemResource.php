<?php

namespace App\Filament\Resources\PackagingItems;

use App\Filament\Resources\PackagingItems\Pages\CreatePackagingItem;
use App\Filament\Resources\PackagingItems\Pages\EditPackagingItem;
use App\Filament\Resources\PackagingItems\Pages\ListPackagingItems;
use App\Filament\Resources\PackagingItems\Schemas\PackagingItemForm;
use App\Filament\Resources\PackagingItems\Tables\PackagingItemsTable;
use App\Models\PackagingItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PackagingItemResource extends Resource
{
    protected static ?string $model = PackagingItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PackagingItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PackagingItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPackagingItems::route('/'),
            'create' => CreatePackagingItem::route('/create'),
            'edit' => EditPackagingItem::route('/{record}/edit'),
        ];
    }
}
