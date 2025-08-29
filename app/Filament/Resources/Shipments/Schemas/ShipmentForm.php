<?php

namespace App\Filament\Resources\Shipments\Schemas;

use App\Models\ProductionBatchItem;
use App\Models\Destination;
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
                                    ->placeholder('e.g., SHP-2025-001'),

                                DatePicker::make('shipment_date')
                                    ->label('Shipment Date')
                                    ->required()
                                    ->default(now()),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('destination_id')
                                    ->label('Destination')
                                    ->required()
                                    ->options(Destination::active()->pluck('name', 'id'))
                                    ->searchable()
                                    ->placeholder('Select a destination'),

                                TextInput::make('delivery_note_number')
                                    ->label('Delivery Note Number')
                                    ->maxLength(100)
                                    ->placeholder('e.g., DN-2025-001'),
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
                                            }),

                                        TextInput::make('qty_shipped')
                                            ->label('Quantity to Ship')
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->live(debounce: 500)
                                            ->placeholder('e.g., 50'),
                                        Textarea::make('notes')
                                            ->label('Item Notes')
                                            ->rows(2)
                                            ->maxLength(500)
                                            ->columnSpan(2)
                                            ->placeholder('Notes specific to this item')
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
                                    ->visible(fn($get): bool => (bool) $get('production_batch_item_id')),


                            ])
                            ->addActionLabel('Add Batch to Ship')
                            ->reorderable(false)
                            ->collapsible()
                            ->minItems(1)
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, Closure $fail) {
                                        if (is_array($value)) {
                                            // Validate no duplicate production batch items
                                            $batchItemIds = array_filter(array_column($value, 'production_batch_item_id'));
                                            $duplicates = array_diff_assoc($batchItemIds, array_unique($batchItemIds));

                                            if (!empty($duplicates)) {
                                                $fail('Each production batch can only be selected once per shipment.');
                                            }

                                            // Summary validation: Check stock availability for all items together
                                            $stockValidationErrors = [];
                                            $batchItemNames = [];

                                            // First pass: Collect all batch items and their details
                                            foreach ($value as $index => $item) {
                                                if (isset($item['production_batch_item_id']) && isset($item['qty_shipped'])) {
                                                    try {
                                                        $batchItem = ProductionBatchItem::with(['product'])->find($item['production_batch_item_id']);
                                                        if ($batchItem) {
                                                            $remainingStock = $batchItem->getRemainingStock();
                                                            $batchItemNames[$item['production_batch_item_id']] = $batchItem->batch_code;

                                                            if ($item['qty_shipped'] > $remainingStock) {
                                                                $stockValidationErrors[] = "Batch {$batchItem->batch_code}: Requested {$item['qty_shipped']} cartons, but only {$remainingStock} available";
                                                            }
                                                        }
                                                    } catch (\Exception $e) {
                                                        $stockValidationErrors[] = "Item " . ($index + 1) . ": Unable to validate stock quantity";
                                                    }
                                                }
                                            }

                                            // If there are stock validation errors, fail with summary message
                                            if (!empty($stockValidationErrors)) {
                                                $fail('Stock validation failed for the following items: ' . implode('; ', $stockValidationErrors));
                                            }
                                        }
                                    };
                                },
                            ]),

                        // Add shipment summary display
                        TextEntry::make('shipment_summary')
                            ->label('Shipment Summary')
                            ->html()
                            ->getStateUsing(function ($get): HtmlString {
                                $shipmentItems = $get('shipmentItems') ?? [];

                                if (empty($shipmentItems)) {
                                    return new HtmlString('<p class="text-gray-500">Add shipment items to see summary.</p>');
                                }

                                try {
                                    $summaryData = [];
                                    $totalItems = 0;
                                    $totalCartons = 0;
                                    $allStockSufficient = true;
                                    $stockIssues = 0;

                                    // Process each shipment item
                                    foreach ($shipmentItems as $item) {
                                        if (isset($item['production_batch_item_id']) && isset($item['qty_shipped'])) {
                                            $batchItem = ProductionBatchItem::with(['product', 'productionBatch'])->find($item['production_batch_item_id']);
                                            if ($batchItem) {
                                                $remainingStock = $batchItem->getRemainingStock();
                                                $isStockSufficient = $item['qty_shipped'] <= $remainingStock;
                                                $allStockSufficient = $allStockSufficient && $isStockSufficient;

                                                if (!$isStockSufficient) {
                                                    $stockIssues++;
                                                }

                                                $summaryData[] = [
                                                    'batch_code' => $batchItem->batch_code,
                                                    'product_name' => $batchItem->product->name,
                                                    'production_date' => $batchItem->productionBatch->production_date->format('Y-m-d'),
                                                    'qty_shipped' => $item['qty_shipped'],
                                                    'remaining_stock' => $remainingStock,
                                                    'is_sufficient' => $isStockSufficient,
                                                    'shortage' => $isStockSufficient ? 0 : ($item['qty_shipped'] - $remainingStock),
                                                ];

                                                $totalItems++;
                                                $totalCartons += $item['qty_shipped'];
                                            }
                                        }
                                    }

                                    if (empty($summaryData)) {
                                        return new HtmlString('<p class="text-gray-500">No valid shipment items to summarize.</p>');
                                    }

                                    $html = '<div class="space-y-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
                                    $html .= '<h5 class="font-semibold text-blue-900 text-lg">📦 Shipment Summary</h5>';

                                    // Overall statistics
                                    $html .= '<div class="grid grid-cols-3 gap-4 p-3 bg-white border border-blue-100 rounded">';
                                    $html .= '<div class="text-center">';
                                    $html .= '<div class="text-2xl font-bold text-blue-600">' . $totalItems . '</div>';
                                    $html .= '<div class="text-sm text-gray-600">Total Batches</div>';
                                    $html .= '</div>';
                                    $html .= '<div class="text-center">';
                                    $html .= '<div class="text-2xl font-bold text-blue-600">' . number_format($totalCartons) . '</div>';
                                    $html .= '<div class="text-sm text-gray-600">Total Cartons</div>';
                                    $html .= '</div>';
                                    $html .= '<div class="text-center">';
                                    $statusIcon = $allStockSufficient ? '✅' : '❌';
                                    $statusText = $allStockSufficient ? 'Ready to Ship' : 'Stock Issues';
                                    $statusColor = $allStockSufficient ? 'text-green-600' : 'text-red-600';
                                    $html .= '<div class="text-2xl font-bold ' . $statusColor . '">' . $statusIcon . '</div>';
                                    $html .= '<div class="text-sm text-gray-600">' . $statusText . '</div>';
                                    $html .= '</div>';
                                    $html .= '</div>';

                                    // Detailed item breakdown
                                    $html .= '<div class="space-y-2">';
                                    $html .= '<h6 class="font-medium text-blue-800">Item Details:</h6>';

                                    foreach ($summaryData as $item) {
                                        $statusIcon = $item['is_sufficient'] ? '✅' : '❌';
                                        $statusColor = $item['is_sufficient'] ? 'text-green-700' : 'text-red-700';
                                        $bgColor = $item['is_sufficient'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';

                                        $html .= '<div class="flex justify-between items-center p-3 ' . $bgColor . ' border rounded">';
                                        $html .= '<div class="flex-1">';
                                        $html .= '<div class="font-medium text-gray-900">' . e($item['batch_code']) . ' - ' . e($item['product_name']) . '</div>';
                                        $html .= '<div class="text-sm text-gray-600">Production Date: ' . e($item['production_date']) . '</div>';
                                        $html .= '</div>';
                                        $html .= '<div class="text-right">';
                                        $html .= '<div class="' . $statusColor . ' font-semibold">' . $statusIcon . ' ' . number_format($item['qty_shipped']) . ' cartons</div>';
                                        $html .= '<div class="text-sm text-gray-600">Available: ' . number_format($item['remaining_stock']) . '</div>';

                                        if (!$item['is_sufficient']) {
                                            $html .= '<div class="text-sm font-medium text-red-600">Short by: ' . number_format($item['shortage']) . '</div>';
                                        }

                                        $html .= '</div>';
                                        $html .= '</div>';
                                    }

                                    $html .= '</div>';

                                    // Overall status summary
                                    if (!$allStockSufficient) {
                                        $html .= '<div class="mt-3 p-3 bg-red-100 border border-red-300 rounded">';
                                        $html .= '<p class="text-red-900 font-semibold">⚠️ STOCK VALIDATION FAILED: ' . $stockIssues . ' item(s) have insufficient stock!</p>';
                                        $html .= '<p class="text-red-800 text-sm mt-1">Shipment cannot be processed until stock issues are resolved.</p>';
                                        $html .= '</div>';
                                    } else {
                                        $html .= '<div class="mt-3 p-3 bg-green-100 border border-green-300 rounded">';
                                        $html .= '<p class="text-green-900 font-semibold">✅ ALL ITEMS VALIDATED</p>';
                                        $html .= '<p class="text-green-800 text-sm mt-1">Shipment is ready to be processed.</p>';
                                        $html .= '</div>';
                                    }

                                    $html .= '</div>';

                                    return new HtmlString($html);
                                } catch (\Exception $e) {
                                    Log::error('Error loading shipment summary: ' . $e->getMessage());
                                    return new HtmlString('<p class="text-red-600 text-sm">Error loading shipment summary.</p>');
                                }
                            })
                            ->columnSpanFull()
                            ->visible(fn($get): bool => !empty($get('shipmentItems'))),
                    ])
                    ->columns(1),
            ]);
    }
}
