<?php

namespace App\Filament\Resources\ProductionBatches;

use App\Filament\Resources\ProductionBatches\Pages\CreateProductionBatch;
use App\Filament\Resources\ProductionBatches\Pages\EditProductionBatch;
use App\Filament\Resources\ProductionBatches\Pages\ListProductionBatches;
use App\Filament\Resources\ProductionBatches\Schemas\ProductionBatchForm;
use App\Filament\Resources\ProductionBatches\Tables\ProductionBatchesTable;
use App\Models\ProductionBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductionBatchResource extends Resource
{
    protected static ?string $model = ProductionBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Production Batches';

    protected static ?string $modelLabel = 'Production Batch';

    protected static ?string $pluralModelLabel = 'Production Batches';

    protected static string|UnitEnum|null $navigationGroup = 'Transactions';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'batch_code';

    public static function form(Schema $schema): Schema
    {
        return ProductionBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionBatchesTable::configure($table);
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
            'index' => ListProductionBatches::route('/'),
            'create' => CreateProductionBatch::route('/create'),
            'edit' => EditProductionBatch::route('/{record}/edit'),
        ];
    }
}
