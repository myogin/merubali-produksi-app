<?php

namespace App\Filament\Resources\ProductionBatches\Schemas;

use App\Models\Bom;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Closure;

class ProductionBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Production Information')
                    ->description('Basic information about the production batch')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('po_number')
                                    ->label('PO Number')
                                    ->maxLength(100)
                                    ->placeholder('e.g., PO-2025-001')
                                    ->helperText('Purchase order number (optional)'),

                                DatePicker::make('production_date')
                                    ->label('Production Date')
                                    ->required()
                                    ->default(now())
                                    ->helperText('Date of production'),
                            ]),

                        Textarea::make('notes')
                            ->label('Production Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('General notes about this production batch'),
                    ])
                    ->columns(1),

                Section::make('Production Items')
                    ->columns(1)
                    ->columnSpanFull()
                    ->description('List of products to produce in this batch')
                    ->schema([
                        Repeater::make('productionBatchItems')
                            ->label('Production Items')
                            ->relationship()
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('batch_code')
                                            ->label('Batch Code (MFD)')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(50)
                                            ->placeholder('e.g., MFD-2025-001')
                                            ->helperText('Unique batch/MFD code'),

                                        Select::make('product_id')
                                            ->label('Product')
                                            ->options(
                                                Product::all()->mapWithKeys(function ($product) {
                                                    return [$product->id => "{$product->product_code} - {$product->name}"];
                                                })
                                            )
                                            ->required()
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function ($set, $get, $state) {
                                                // Clear qty when product changes to trigger BOM recalculation
                                                $set('qty_produced', null);
                                            })
                                            ->helperText('Select the product to produce'),

                                        TextInput::make('qty_produced')
                                            ->label('Quantity to Produce')
                                            ->required()
                                            ->integer()
                                            ->minValue(1)
                                            ->live(debounce: 500)
                                            ->placeholder('e.g., 100')
                                            ->helperText('Number of cartons to produce')
                                            ->afterStateUpdated(function ($set, $get, $state) {
                                                // Show notification about individual item requirements
                                                // Note: Final validation will be done cumulatively across all items
                                                if ($state && $get('product_id')) {
                                                    try {
                                                        $boms = Bom::where('product_id', $get('product_id'))
                                                            ->with('packagingItem')
                                                            ->get();

                                                        $insufficientMaterials = [];

                                                        foreach ($boms as $bom) {
                                                            $requiredQty = $bom->qty_per_unit * $state;
                                                            $currentStock = StockMovement::where('item_type', 'packaging')
                                                                ->where('item_id', $bom->packaging_item_id)
                                                                ->selectRaw('SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
                                                                ->value('current_stock') ?? 0;

                                                            if ($currentStock < $requiredQty) {
                                                                $insufficientMaterials[] = $bom->packagingItem->name;
                                                            }
                                                        }

                                                        if (!empty($insufficientMaterials)) {
                                                            Notification::make()
                                                                ->title('Material Warning')
                                                                ->body('This item alone requires more than available stock for: ' . implode(', ', $insufficientMaterials) . '. Check total requirements below.')
                                                                ->warning()
                                                                ->send();
                                                        }
                                                    } catch (\Exception $e) {
                                                        Notification::make()
                                                            ->title('Validation Error')
                                                            ->body('Unable to validate material requirements.')
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }
                                            })
                                            ->rules([
                                                function () {
                                                    return function (string $attribute, $value, Closure $fail) {
                                                        if ($value) {
                                                            // Extract the item index from the attribute path
                                                            $segments = explode('.', $attribute);
                                                            $itemIndex = $segments[1] ?? null;

                                                            if ($itemIndex !== null) {
                                                                $formData = request()->input('data', []);
                                                                $productId = $formData['productionBatchItems'][$itemIndex]['product_id'] ?? null;

                                                                if ($productId) {
                                                                    try {
                                                                        $boms = Bom::where('product_id', $productId)->with('packagingItem')->get();

                                                                        foreach ($boms as $bom) {
                                                                            $requiredQty = $bom->qty_per_unit * $value;
                                                                            $currentStock = StockMovement::where('item_type', 'packaging')
                                                                                ->where('item_id', $bom->packaging_item_id)
                                                                                ->selectRaw('SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
                                                                                ->value('current_stock') ?? 0;

                                                                            if ($currentStock < $requiredQty) {
                                                                                $fail("Insufficient stock for {$bom->packagingItem->name}. Required: {$requiredQty}, Available: {$currentStock}");
                                                                                break;
                                                                            }
                                                                        }
                                                                    } catch (\Exception $e) {
                                                                        $fail('Unable to validate material requirements.');
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    };
                                                },
                                            ]),

                                        TextInput::make('uom')
                                            ->label('Unit')
                                            ->required()
                                            ->default('cartons')
                                            ->disabled()
                                            ->helperText('Unit is always cartons'),
                                    ]),

                                Textarea::make('notes')
                                    ->label('Item Notes')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->placeholder('Notes specific to this production item')
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('Add Production Item')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                isset($state['batch_code']) && isset($state['qty_produced'])
                                    ? "Batch: {$state['batch_code']} - {$state['qty_produced']} cartons"
                                    : 'New Production Item'
                            )
                            ->minItems(1)
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, Closure $fail) {
                                        // Validate no duplicate batch codes
                                        if (is_array($value)) {
                                            $batchCodes = array_filter(array_column($value, 'batch_code'));
                                            $duplicates = array_diff_assoc($batchCodes, array_unique($batchCodes));

                                            if (!empty($duplicates)) {
                                                $fail('Each batch code must be unique within the production batch.');
                                            }

                                            // Validate cumulative material availability for all items
                                            $cumulativeRequirements = [];
                                            $packagingItemNames = [];

                                            // First pass: Calculate cumulative requirements for each packaging item
                                            foreach ($value as $index => $item) {
                                                if (isset($item['product_id']) && isset($item['qty_produced'])) {
                                                    try {
                                                        $boms = Bom::where('product_id', $item['product_id'])->with('packagingItem')->get();

                                                        foreach ($boms as $bom) {
                                                            $requiredQty = $bom->qty_per_unit * $item['qty_produced'];
                                                            $packagingItemId = $bom->packaging_item_id;

                                                            // Store packaging item name for error messages
                                                            $packagingItemNames[$packagingItemId] = $bom->packagingItem->name;

                                                            // Accumulate requirements
                                                            if (!isset($cumulativeRequirements[$packagingItemId])) {
                                                                $cumulativeRequirements[$packagingItemId] = 0;
                                                            }
                                                            $cumulativeRequirements[$packagingItemId] += $requiredQty;
                                                        }
                                                    } catch (\Exception $e) {
                                                        $fail("Item " . ($index + 1) . ": Unable to validate material requirements.");
                                                        return;
                                                    }
                                                }
                                            }

                                            // Second pass: Validate cumulative requirements against available stock
                                            if (!empty($cumulativeRequirements)) {
                                                try {
                                                    // Get current stock for all required packaging items in one query
                                                    $packagingItemIds = array_keys($cumulativeRequirements);
                                                    $stockData = StockMovement::where('item_type', 'packaging')
                                                        ->whereIn('item_id', $packagingItemIds)
                                                        ->selectRaw('item_id, SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
                                                        ->groupBy('item_id')
                                                        ->pluck('current_stock', 'item_id');

                                                    $insufficientMaterials = [];

                                                    foreach ($cumulativeRequirements as $packagingItemId => $totalRequired) {
                                                        $currentStock = $stockData[$packagingItemId] ?? 0;

                                                        if ($currentStock < $totalRequired) {
                                                            $materialName = $packagingItemNames[$packagingItemId];
                                                            $shortage = $totalRequired - $currentStock;
                                                            $insufficientMaterials[] = "{$materialName}: Required {$totalRequired}, Available {$currentStock} (Short by {$shortage})";
                                                        }
                                                    }

                                                    if (!empty($insufficientMaterials)) {
                                                        $fail('Insufficient stock for materials across all production items: ' . implode('; ', $insufficientMaterials));
                                                    }
                                                } catch (\Exception $e) {
                                                    $fail('Unable to validate cumulative material requirements.');
                                                }
                                            }
                                        }
                                    };
                                },
                            ]),

                        // Add cumulative material requirements summary
                        TextEntry::make('cumulative_material_requirements')
                            ->label('Total Material Requirements Summary')
                            ->html()
                            ->getStateUsing(function ($get): HtmlString {
                                $productionItems = $get('productionBatchItems') ?? [];

                                if (empty($productionItems)) {
                                    return new HtmlString('<p class="text-gray-500">Add production items to see total material requirements.</p>');
                                }

                                try {
                                    $cumulativeRequirements = [];
                                    $packagingItemNames = [];
                                    $packagingItemUoms = [];

                                    // Calculate cumulative requirements for each packaging item
                                    foreach ($productionItems as $item) {
                                        if (isset($item['product_id']) && isset($item['qty_produced'])) {
                                            $boms = Bom::where('product_id', $item['product_id'])->with('packagingItem')->get();

                                            foreach ($boms as $bom) {
                                                $requiredQty = $bom->qty_per_unit * $item['qty_produced'];
                                                $packagingItemId = $bom->packaging_item_id;

                                                // Store packaging item details
                                                $packagingItemNames[$packagingItemId] = $bom->packagingItem->name;
                                                $packagingItemUoms[$packagingItemId] = $bom->uom;

                                                // Accumulate requirements
                                                if (!isset($cumulativeRequirements[$packagingItemId])) {
                                                    $cumulativeRequirements[$packagingItemId] = 0;
                                                }
                                                $cumulativeRequirements[$packagingItemId] += $requiredQty;
                                            }
                                        }
                                    }

                                    if (empty($cumulativeRequirements)) {
                                        return new HtmlString('<p class="text-gray-500">No material requirements calculated yet.</p>');
                                    }

                                    // Get current stock for all required packaging items
                                    $packagingItemIds = array_keys($cumulativeRequirements);
                                    $stockData = StockMovement::where('item_type', 'packaging')
                                        ->whereIn('item_id', $packagingItemIds)
                                        ->selectRaw('item_id, SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
                                        ->groupBy('item_id')
                                        ->pluck('current_stock', 'item_id');

                                    $html = '<div class="space-y-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
                                    $html .= '<h5 class="font-semibold text-blue-900 text-lg">📋 Total Material Requirements for All Production Items</h5>';

                                    $allStockSufficient = true;
                                    $totalShortage = 0;

                                    foreach ($cumulativeRequirements as $packagingItemId => $totalRequired) {
                                        $currentStock = $stockData[$packagingItemId] ?? 0;
                                        $materialName = $packagingItemNames[$packagingItemId];
                                        $uom = $packagingItemUoms[$packagingItemId];

                                        $isStockSufficient = $currentStock >= $totalRequired;
                                        $allStockSufficient = $allStockSufficient && $isStockSufficient;

                                        $statusIcon = $isStockSufficient ? '✅' : '❌';
                                        $statusColor = $isStockSufficient ? 'text-green-700' : 'text-red-700';
                                        $bgColor = $isStockSufficient ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';

                                        $html .= '<div class="flex justify-between items-center p-3 ' . $bgColor . ' border rounded">';
                                        $html .= '<div>';
                                        $html .= '<span class="font-medium text-gray-900">' . e($materialName) . '</span>';
                                        $html .= '</div>';
                                        $html .= '<div class="text-right">';
                                        $html .= '<div class="' . $statusColor . ' font-semibold">' . $statusIcon . ' ' . number_format($totalRequired) . ' ' . e($uom) . '</div>';
                                        $html .= '<div class="text-sm text-gray-600">Available: ' . number_format($currentStock) . '</div>';

                                        if (!$isStockSufficient) {
                                            $shortage = $totalRequired - $currentStock;
                                            $totalShortage++;
                                            $html .= '<div class="text-sm font-medium text-red-600">Short by: ' . number_format($shortage) . '</div>';
                                        }

                                        $html .= '</div>';
                                        $html .= '</div>';
                                    }

                                    // Overall status summary
                                    if (!$allStockSufficient) {
                                        $html .= '<div class="mt-3 p-3 bg-red-100 border border-red-300 rounded">';
                                        $html .= '<p class="text-red-900 font-semibold">⚠️ INSUFFICIENT STOCK: ' . $totalShortage . ' material(s) have insufficient stock!</p>';
                                        $html .= '<p class="text-red-800 text-sm mt-1">Production cannot proceed until stock is replenished.</p>';
                                        $html .= '</div>';
                                    } else {
                                        $html .= '<div class="mt-3 p-3 bg-green-100 border border-green-300 rounded">';
                                        $html .= '<p class="text-green-900 font-semibold">✅ ALL MATERIALS AVAILABLE</p>';
                                        $html .= '<p class="text-green-800 text-sm mt-1">Production can proceed with current stock levels.</p>';
                                        $html .= '</div>';
                                    }

                                    $html .= '</div>';

                                    return new HtmlString($html);
                                } catch (\Exception $e) {
                                    Log::error('Error loading cumulative material requirements: ' . $e->getMessage());
                                    return new HtmlString('<p class="text-red-600 text-sm">Error loading cumulative material requirements.</p>');
                                }
                            })
                            ->columnSpanFull()
                            ->visible(fn($get): bool => !empty($get('productionBatchItems'))),
                    ])
                    ->columns(1),
            ]);
    }
}
