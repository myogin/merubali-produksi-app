<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('movement_date')
                    ->required(),
                Select::make('item_type')
                    ->options(['packaging' => 'Packaging', 'finished_goods' => 'Finished goods'])
                    ->required(),
                TextInput::make('item_id')
                    ->required()
                    ->numeric(),
                TextInput::make('batch_id')
                    ->numeric(),
                TextInput::make('qty')
                    ->required()
                    ->numeric(),
                TextInput::make('uom')
                    ->required(),
                Select::make('movement_type')
                    ->options(['in' => 'In', 'out' => 'Out'])
                    ->required(),
                TextInput::make('reference_type')
                    ->required(),
                TextInput::make('reference_id')
                    ->required()
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
