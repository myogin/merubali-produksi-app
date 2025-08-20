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
                                    ->placeholder('e.g., RCP-2025-001')
                                    ->helperText('Unique receipt number'),

                                DatePicker::make('receipt_date')
                                    ->label('Receipt Date')
                                    ->required()
                                    ->default(now())
                                    ->helperText('Date when packaging was received'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('supplier_name')
                                    ->label('Supplier Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., PT. Supplier Kemasan')
                                    ->helperText('Name of the packaging supplier'),

                                TextInput::make('delivery_note_url')
                                    ->label('Delivery Note URL')
                                    ->url()
                                    ->maxLength(500)
                                    ->placeholder('https://drive.google.com/...')
                                    ->helperText('Link to delivery note document'),
                            ]),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Additional notes about this receipt'),
                    ])
                    ->columns(1),

                Section::make('Receipt Items')
                    ->description('List of packaging items received')
                    ->schema([
                        Repeater::make('receiptItems')
                            ->label('Items')
                            ->relationship()
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('packaging_item_id')
                                            ->label('Packaging Item')
                                            ->required()
                                            ->relationship('packagingItem', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->getOptionLabelFromRecordUsing(fn(PackagingItem $record): string => "{$record->packaging_code} - {$record->name}")
                                            ->helperText('Select packaging item'),

                                        TextInput::make('qty_received')
                                            ->label('Quantity Received')
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->placeholder('e.g., 1000')
                                            ->helperText('Quantity received'),

                                        TextInput::make('uom')
                                            ->label('Unit')
                                            ->required()
                                            ->default('pcs')
                                            ->placeholder('pcs')
                                            ->helperText('Unit of measure'),
                                    ]),

                                Textarea::make('notes')
                                    ->label('Item Notes')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->placeholder('Notes specific to this item'),
                            ])
                            ->addActionLabel('Add Item')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                isset($state['packaging_item_id']) && isset($state['qty_received'])
                                    ? "Item: {$state['qty_received']} units"
                                    : 'New Item'
                            )
                            ->minItems(1),
                    ])
                    ->columns(1),
            ]);
    }
}
