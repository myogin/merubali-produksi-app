<?php

namespace App\Filament\Resources\Shipments\Schemas;

use App\Models\ProductionBatch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ShipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shipment Information')
                    ->description('Basic information about the shipment')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('shipment_number')
                                    ->label('Shipment Number')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('e.g., SHP-2025-001')
                                    ->helperText('Unique shipment number'),

                                DatePicker::make('shipment_date')
                                    ->label('Shipment Date')
                                    ->required()
                                    ->default(now())
                                    ->helperText('Date of shipment'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('destination')
                                    ->label('Destination')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Jakarta, Indonesia')
                                    ->helperText('Shipment destination'),

                                TextInput::make('delivery_note_number')
                                    ->label('Delivery Note Number')
                                    ->maxLength(100)
                                    ->placeholder('e.g., DN-2025-001')
                                    ->helperText('Delivery note reference (optional)'),
                            ]),

                        Textarea::make('notes')
                            ->label('Shipment Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Additional notes about this shipment'),
                    ])
                    ->columns(1),

                Section::make('Shipment Items')
                    ->description('Select production batches and quantities to ship')
                    ->schema([
                        Repeater::make('shipmentItems')
                            ->label('Items to Ship')
                            ->relationship()
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('production_batch_id')
                                            ->label('Production Batch')
                                            ->required()
                                            ->options(function () {
                                                return ProductionBatch::with('product')
                                                    ->whereHas('stockMovements', function ($query) {
                                                        $query->where('item_type', 'finished_goods')
                                                            ->where('movement_type', 'in');
                                                    })
                                                    ->get()
                                                    ->filter(function ($batch) {
                                                        return $batch->getRemainingStock() > 0;
                                                    })
                                                    ->mapWithKeys(function ($batch) {
                                                        $remaining = $batch->getRemainingStock();
                                                        return [
                                                            $batch->id => "{$batch->batch_code} - {$batch->product->name} (Available: {$remaining} cartons)"
                                                        ];
                                                    });
                                            })
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                // Clear qty when batch changes
                                                $set('qty_shipped', null);
                                            })
                                            ->helperText('Select batch with available stock'),

                                        TextInput::make('qty_shipped')
                                            ->label('Quantity to Ship')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0.001)
                                            ->step(0.001)
                                            ->live(debounce: 500)
                                            ->placeholder('e.g., 50')
                                            ->helperText('Quantity to ship (cartons)'),

                                        TextInput::make('uom')
                                            ->label('Unit')
                                            ->required()
                                            ->default('cartons')
                                            ->disabled()
                                            ->helperText('Unit is always cartons'),
                                    ]),

                                TextEntry::make('batch_info')
                                    ->label('Batch Information')
                                    ->html() // allow rendering HTML
                                    ->getStateUsing(function (Get $get): HtmlString {
                                        $batchId = $get('production_batch_id');
                                        $qtyToShip = $get('qty_shipped');

                                        if (!$batchId) {
                                            return new HtmlString('<p class="text-gray-500">Select a batch to see information.</p>');
                                        }

                                        $batch = ProductionBatch::with('product')->find($batchId);
                                        if (!$batch) {
                                            return new HtmlString('<p class="text-red-600">Batch not found.</p>');
                                        }

                                        $remainingStock = $batch->getRemainingStock();
                                        $totalShipped = $batch->getTotalShipped();

                                        $html = '<div class="space-y-2">';
                                        $html .= '<div class="grid grid-cols-2 gap-4 text-sm">';
                                        $html .= '<div><strong>Product:</strong> ' . e($batch->product->name) . '</div>';
                                        $html .= '<div><strong>Production Date:</strong> ' . e($batch->production_date->format('Y-m-d')) . '</div>';
                                        $html .= '<div><strong>Total Produced:</strong> ' . number_format($batch->qty_produced, 3) . ' cartons</div>';
                                        $html .= '<div><strong>Already Shipped:</strong> ' . number_format($totalShipped, 3) . ' cartons</div>';
                                        $html .= '<div><strong>Available Stock:</strong> ' . number_format($remainingStock, 3) . ' cartons</div>';
                                        $html .= '</div>';

                                        if ($qtyToShip) {
                                            if ($qtyToShip > $remainingStock) {
                                                $html .= '<div class="mt-3 p-2 bg-red-50 border border-red-200 rounded">';
                                                $html .= '<p class="text-red-800 text-sm font-medium">⚠️ Quantity exceeds available stock!</p>';
                                                $html .= '</div>';
                                            } else {
                                                $newRemaining = $remainingStock - $qtyToShip;
                                                $html .= '<div class="mt-3 p-2 bg-green-50 border border-green-200 rounded">';
                                                $html .= '<p class="text-green-800 text-sm"><strong>After shipment:</strong> ' . number_format($newRemaining, 3) . ' cartons remaining</p>';
                                                $html .= '</div>';
                                            }
                                        }

                                        $html .= '</div>';

                                        return new HtmlString($html);
                                    })
                                    ->columnSpanFull()
                                    ->visible(fn(Get $get): bool => (bool) $get('production_batch_id')),

                                Textarea::make('notes')
                                    ->label('Item Notes')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->placeholder('Notes specific to this item')
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('Add Batch to Ship')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                isset($state['production_batch_id']) && isset($state['qty_shipped'])
                                    ? "Batch: {$state['qty_shipped']} cartons"
                                    : 'New Item'
                            )
                            ->minItems(1),
                    ])
                    ->columns(1),
            ]);
    }
}
