<?php

namespace App\Filament\Resources\Boms;

use App\Filament\Resources\Boms\Pages\CreateBom;
use App\Filament\Resources\Boms\Pages\EditBom;
use App\Filament\Resources\Boms\Pages\ListBoms;
use App\Filament\Resources\Boms\Schemas\BomForm;
use App\Filament\Resources\Boms\Tables\BomsTable;
use App\Models\Bom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BomResource extends Resource
{
    protected static ?string $model = Bom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Bill of Materials';

    protected static ?string $pluralModelLabel = 'Bill of Materials';

    public static function form(Schema $schema): Schema
    {
        return BomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BomsTable::configure($table);
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
            'index' => ListBoms::route('/'),
            'create' => CreateBom::route('/create'),
            'edit' => EditBom::route('/{record}/edit'),
        ];
    }
}
