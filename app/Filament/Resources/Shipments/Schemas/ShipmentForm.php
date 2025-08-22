<?php

namespace App\Filament\Resources\Shipments\Schemas;

use App\Models\ProductionBatchItem;
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
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use Closure;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
                    ->columns(1)
                    ->columnSpanFull()
                    ->description('Select production batches and quantities to ship')
                    ->schema([
                        Repeater::make('shipmentItems')
                            ->label('Items to Ship')
                            ->relationship()
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        Select::make('production_batch_item_id')
                                            ->label('Production Batch')
                                            ->required()
                                            ->options(function () {
                                                // Cache the query to avoid repeated database calls
                                                static $cachedOptions = null;

                                                if ($cachedOptions === null) {
                                                    try {
                                                        $batchItems = ProductionBatchItem::with(['product', 'shipmentItems'])
                                                            ->whereHas('stockMovements', function ($query) {
                                                                $query->where('item_type', 'finished_goods')
                                                                    ->where('movement_type', 'in');
                                                            })
                                                            ->get();

                                                        $cachedOptions = $batchItems
                                                            ->filter(function ($batchItem) {
                                                                return $batchItem->getRemainingStock() > 0;
                                                            })
                                                            ->mapWithKeys(function ($batchItem) {
                                                                $remaining = $batchItem->getRemainingStock();
                                                                return [
                                                                    $batchItem->id => "{$batchItem->batch_code} - {$batchItem->product->name} (Available: {$remaining} cartons)"
                                                                ];
                                                            })
                                                            ->toArray();
                                                    } catch (\Exception $e) {
                                                        // Handle database errors gracefully
                                                        $cachedOptions = [];
                                                        Log::error('Error loading production batch items for shipment form: ' . $e->getMessage());
                                                    }
                                                }

                                                return $cachedOptions;
                                            })
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function ($set, $get, $state) {
                                                // Clear qty when batch changes and validate selection
                                                $set('qty_shipped', null);

                                                // Validate that the batch is not already selected in other items
                                                if ($state) {
                                                    $allItems = $get('../../shipmentItems') ?? [];
                                                    $currentPath = $get('../');
                                                    $duplicateCount = 0;

                                                    foreach ($allItems as $item) {
                                                        if (isset($item['production_batch_item_id']) && $item['production_batch_item_id'] == $state) {
                                                            $duplicateCount++;
                                                        }
                                                    }

                                                    if ($duplicateCount > 1) {
                                                        Notification::make()
                                                            ->title('Duplicate Batch Selected')
                                                            ->body('This production batch is already selected in another item.')
                                                            ->warning()
                                                            ->send();
                                                    }
                                                }
                                            })
                                            ->helperText('Select batch with available stock'),

                                        TextInput::make('qty_shipped')
                                            ->label('Quantity to Ship')
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->live(debounce: 500)
                                            ->placeholder('e.g., 50')
                                            ->helperText('Quantity to ship (cartons)')
                                            ->afterStateUpdated(function ($set, $get, $state) {
                                                // Validate quantity against available stock
                                                if ($state && $get('production_batch_item_id')) {
                                                    try {
                                                        $batchItem = ProductionBatchItem::find($get('production_batch_item_id'));
                                                        if ($batchItem) {
                                                            $remainingStock = $batchItem->getRemainingStock();
                                                            if ($state > $remainingStock) {
                                                                Notification::make()
                                                                    ->title('Insufficient Stock')
                                                                    ->body("Only {$remainingStock} cartons available for this batch.")
                                                                    ->warning()
                                                                    ->send();
                                                            }
                                                        }
                                                    } catch (\Exception $e) {
                                                        // Handle any database errors gracefully
                                                        Notification::make()
                                                            ->title('Validation Error')
                                                            ->body('Unable to validate stock quantity.')
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }
                                            })
                                            ->rules([
                                                function () {
                                                    return function (string $attribute, $value, Closure $fail) {
                                                        // Extract the batch ID from the form state
                                                        $segments = explode('.', $attribute);
                                                        $itemIndex = $segments[1] ?? null;

                                                        if ($itemIndex !== null) {
                                                            $formData = request()->input('data', []);
                                                            $batchItemId = $formData['shipmentItems'][$itemIndex]['production_batch_item_id'] ?? null;

                                                            if ($batchItemId && $value) {
                                                                try {
                                                                    $batchItem = ProductionBatchItem::find($batchItemId);
                                                                    if ($batchItem) {
                                                                        $remainingStock = $batchItem->getRemainingStock();
                                                                        if ($value > $remainingStock) {
                                                                            $fail("Quantity ({$value}) exceeds available stock ({$remainingStock} cartons).");
                                                                        }
                                                                    }
                                                                } catch (\Exception $e) {
                                                                    $fail('Unable to validate stock quantity.');
                                                                }
                                                            }
                                                        }
                                                    };
                                                },
                                            ]),

                                        TextEntry::make('batch_info')
                                            ->label('Batch Information')
                                            ->html() // allow rendering HTML
                                            ->getStateUsing(function ($get): HtmlString {
                                                $batchItemId = $get('production_batch_item_id');
                                                $qtyToShip = $get('qty_shipped');

                                                if (!$batchItemId) {
                                                    return new HtmlString('<p class="text-gray-500">Select a batch to see information.</p>');
                                                }

                                                $batchItem = ProductionBatchItem::with(['product', 'productionBatch'])->find($batchItemId);
                                                if (!$batchItem) {
                                                    return new HtmlString('<p class="text-red-600">Batch not found.</p>');
                                                }

                                                $remainingStock = $batchItem->getRemainingStock();
                                                $totalShipped = $batchItem->getTotalShipped();

                                                $html = '<div class="space-y-2">';
                                                $html .= '<div class="grid grid-cols-2 gap-4 text-sm">';
                                                $html .= '<div><strong>Product:</strong> ' . e($batchItem->product->name) . '</div>';
                                                $html .= '<div><strong>Production Date:</strong> ' . e($batchItem->productionBatch->production_date->format('Y-m-d')) . '</div>';
                                                $html .= '<div><strong>Total Produced:</strong> ' . number_format($batchItem->qty_produced) . ' cartons</div>';
                                                $html .= '<div><strong>Already Shipped:</strong> ' . number_format($totalShipped) . ' cartons</div>';
                                                $html .= '<div><strong>Available Stock:</strong> ' . number_format($remainingStock) . ' cartons</div>';
                                                $html .= '</div>';

                                                if ($qtyToShip) {
                                                    if ($qtyToShip > $remainingStock) {
                                                        $html .= '<div class="mt-3 p-2 bg-red-50 border border-red-200 rounded">';
                                                        $html .= '<p class="text-red-800 text-sm font-medium">⚠️ Quantity exceeds available stock!</p>';
                                                        $html .= '</div>';
                                                    } else {
                                                        $newRemaining = $remainingStock - $qtyToShip;
                                                        $html .= '<div class="mt-3 p-2 bg-green-50 border border-green-200 rounded">';
                                                        $html .= '<p class="text-green-800 text-sm"><strong>After shipment:</strong> ' . number_format($newRemaining) . ' cartons remaining</p>';
                                                        $html .= '</div>';
                                                    }
                                                }

                                                $html .= '</div>';

                                                return new HtmlString($html);
                                            })
                                            ->columnSpanFull()
                                            ->visible(fn($get): bool => (bool) $get('production_batch_item_id')),

                                        Textarea::make('notes')
                                            ->label('Item Notes')
                                            ->rows(2)
                                            ->columnSpan(2)
                                            ->maxLength(500)
                                            ->placeholder('Notes specific to this item')
                                    ])
                            ])
                            ->addActionLabel('Add Batch to Ship')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                isset($state['production_batch_item_id']) && isset($state['qty_shipped'])
                                    ? "Batch: {$state['qty_shipped']} cartons"
                                    : 'New Item'
                            )
                            ->minItems(1)
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, Closure $fail) {
                                        // Validate no duplicate production batch items
                                        if (is_array($value)) {
                                            $batchItemIds = array_filter(array_column($value, 'production_batch_item_id'));
                                            $duplicates = array_diff_assoc($batchItemIds, array_unique($batchItemIds));

                                            if (!empty($duplicates)) {
                                                $fail('Each production batch can only be selected once per shipment.');
                                            }

                                            // Validate stock availability for all items
                                            foreach ($value as $index => $item) {
                                                if (isset($item['production_batch_item_id']) && isset($item['qty_shipped'])) {
                                                    try {
                                                        $batchItem = ProductionBatchItem::find($item['production_batch_item_id']);
                                                        if ($batchItem) {
                                                            $remainingStock = $batchItem->getRemainingStock();
                                                            if ($item['qty_shipped'] > $remainingStock) {
                                                                $fail("Item " . ($index + 1) . ": Quantity ({$item['qty_shipped']}) exceeds available stock ({$remainingStock} cartons) for batch {$batchItem->batch_code}.");
                                                            }
                                                        }
                                                    } catch (\Exception $e) {
                                                        $fail("Item " . ($index + 1) . ": Unable to validate stock quantity.");
                                                    }
                                                }
                                            }
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
