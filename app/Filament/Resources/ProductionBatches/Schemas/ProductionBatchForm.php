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
                                                // Validate material availability when quantity changes
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
                                                                ->title('Insufficient Materials')
                                                                ->body('Not enough stock for: ' . implode(', ', $insufficientMaterials))
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

                                TextEntry::make('bom_requirements')
                                    ->label('Material Requirements')
                                    ->html() // enable HTML output
                                    ->getStateUsing(function ($get): HtmlString {
                                        $productId = $get('product_id');
                                        $qtyToProduce = $get('qty_produced');

                                        if (!$productId || !$qtyToProduce) {
                                            return new HtmlString('<p class="text-gray-500">Select a product and quantity to see material requirements.</p>');
                                        }

                                        try {
                                            $boms = Bom::where('product_id', $productId)
                                                ->with('packagingItem')
                                                ->get();

                                            if ($boms->isEmpty()) {
                                                return new HtmlString('<p class="text-red-600 font-medium">⚠️ No BOM found for this product!</p>');
                                            }

                                            // Get all packaging item IDs to optimize stock queries
                                            $packagingItemIds = $boms->pluck('packaging_item_id')->unique();

                                            // Fetch all stock data in one query
                                            $stockData = StockMovement::where('item_type', 'packaging')
                                                ->whereIn('item_id', $packagingItemIds)
                                                ->selectRaw('item_id, SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
                                                ->groupBy('item_id')
                                                ->pluck('current_stock', 'item_id');

                                            $html = '<div class="space-y-2">';
                                            $html .= '<h5 class="font-medium text-gray-900">Required Materials:</h5>';

                                            $allStockSufficient = true;

                                            foreach ($boms as $bom) {
                                                $requiredQty = $bom->qty_per_unit * $qtyToProduce;
                                                $currentStock = $stockData[$bom->packaging_item_id] ?? 0;

                                                $isStockSufficient = $currentStock >= $requiredQty;
                                                $allStockSufficient = $allStockSufficient && $isStockSufficient;

                                                $statusIcon = $isStockSufficient ? '✅' : '❌';
                                                $statusColor = $isStockSufficient ? 'text-green-600' : 'text-red-600';

                                                $html .= '<div class="flex justify-between items-center p-2 bg-gray-50 rounded text-sm">';
                                                $html .= '<div>';
                                                $html .= '<span class="font-medium">' . e($bom->packagingItem->name) . '</span>';
                                                $html .= '</div>';
                                                $html .= '<div class="text-right">';
                                                $html .= '<div class="' . $statusColor . ' font-medium">' . $statusIcon . ' ' . number_format($requiredQty) . ' ' . e($bom->uom) . '</div>';
                                                $html .= '<div class="text-xs text-gray-600">Available: ' . number_format($currentStock) . '</div>';
                                                $html .= '</div>';
                                                $html .= '</div>';
                                            }

                                            if (!$allStockSufficient) {
                                                $html .= '<div class="mt-2 p-2 bg-red-50 border border-red-200 rounded">';
                                                $html .= '<p class="text-red-800 text-xs font-medium">⚠️ Insufficient stock for some materials!</p>';
                                                $html .= '</div>';
                                            } else {
                                                $html .= '<div class="mt-2 p-2 bg-green-50 border border-green-200 rounded">';
                                                $html .= '<p class="text-green-800 text-xs font-medium">✅ All materials available!</p>';
                                                $html .= '</div>';
                                            }

                                            $html .= '</div>';

                                            return new HtmlString($html);
                                        } catch (\Exception $e) {
                                            Log::error('Error loading BOM requirements for production batch item: ' . $e->getMessage());
                                            return new HtmlString('<p class="text-red-600 text-sm">Error loading material requirements.</p>');
                                        }
                                    })
                                    ->columnSpanFull()
                                    ->visible(fn($get): bool => $get('product_id') && $get('qty_produced')),

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

                                            // Validate material availability for all items
                                            foreach ($value as $index => $item) {
                                                if (isset($item['product_id']) && isset($item['qty_produced'])) {
                                                    try {
                                                        $boms = Bom::where('product_id', $item['product_id'])->with('packagingItem')->get();

                                                        foreach ($boms as $bom) {
                                                            $requiredQty = $bom->qty_per_unit * $item['qty_produced'];
                                                            $currentStock = StockMovement::where('item_type', 'packaging')
                                                                ->where('item_id', $bom->packaging_item_id)
                                                                ->selectRaw('SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
                                                                ->value('current_stock') ?? 0;

                                                            if ($currentStock < $requiredQty) {
                                                                $fail("Item " . ($index + 1) . ": Insufficient stock for {$bom->packagingItem->name}. Required: {$requiredQty}, Available: {$currentStock}");
                                                                break 2; // Break out of both loops
                                                            }
                                                        }
                                                    } catch (\Exception $e) {
                                                        $fail("Item " . ($index + 1) . ": Unable to validate material requirements.");
                                                        break;
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
