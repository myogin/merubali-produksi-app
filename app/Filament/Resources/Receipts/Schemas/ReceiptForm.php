<?php

namespace App\Filament\Resources\Receipts\Schemas;

use App\Models\PackagingItem;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Receipt Information')
                    ->description('Basic information about the packaging receipt')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('receipt_number')
                                    ->label('Receipt Number')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('e.g., RCP-2025-001'),

                                DatePicker::make('receipt_date')
                                    ->label('Receipt Date')
                                    ->required()
                                    ->default(now()),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('supplier_name')
                                    ->label('Supplier Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., PT. Supplier Kemasan'),

                                TextInput::make('delivery_note_url')
                                    ->label('Delivery Note URL')
                                    ->url()
                                    ->maxLength(500)
                                    ->placeholder('https://drive.google.com/...'),
                            ]),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Additional notes about this receipt'),
                    ])
                    ->columns(1),

                Section::make('Receipt Items')
                    ->columns(1)
                    ->columnSpanFull()
                    ->description('List of packaging items received')
                    ->schema([
                        // Column headers
                        Grid::make(4)
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('packaging_item_header')
                                    ->content('')
                                    ->extraAttributes(['class' => 'font-semibold text-gray-700 text-sm']),

                                \Filament\Forms\Components\Placeholder::make('quantity_header')
                                    ->content('')
                                    ->extraAttributes(['class' => 'font-semibold text-gray-700 text-sm']),

                                \Filament\Forms\Components\Placeholder::make('unit_header')
                                    ->content('')
                                    ->extraAttributes(['class' => 'font-semibold text-gray-700 text-sm']),

                                \Filament\Forms\Components\Placeholder::make('notes_header')
                                    ->content('')
                                    ->extraAttributes(['class' => 'font-semibold text-gray-700 text-sm']),
                            ])
                            ->columnSpanFull(),

                        Repeater::make('receiptItems')
                            ->label('')
                            ->relationship()
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        Select::make('packaging_item_id')
                                            ->hiddenLabel()
                                            ->required()
                                            ->relationship('packagingItem', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->getOptionLabelFromRecordUsing(fn(PackagingItem $record): string => "{$record->packaging_code} - {$record->name}"),

                                        TextInput::make('qty_received')
                                            ->hiddenLabel()
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->placeholder('e.g., 1000'),

                                        TextInput::make('uom')
                                            ->hiddenLabel()
                                            ->required()
                                            ->default('pcs')
                                            ->placeholder('pcs'),

                                        Textarea::make('notes')
                                            ->hiddenLabel()
                                            ->rows(2)
                                            ->maxLength(500)
                                            ->placeholder('Notes specific to this item'),
                                    ]),
                            ])
                            ->addActionLabel('Add Item')
                            ->reorderable(false)
                            // ->collapsible()
                            ->minItems(1),
                    ])
                    ->columns(1),
            ]);
    }
}
