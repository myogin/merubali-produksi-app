<?php

namespace App\Filament\Resources\ProductionBatches\Schemas;

use App\Models\Bom;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                                TextInput::make('batch_code')
                                    ->label('Batch Code (MFD)')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('e.g., MFD-2025-001')
                                    ->helperText('Unique batch/MFD code'),

                                DatePicker::make('production_date')
                                    ->label('Production Date')
                                    ->required()
                                    ->default(now())
                                    ->helperText('Date of production'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('po_number')
                                    ->label('PO Number')
                                    ->maxLength(100)
                                    ->placeholder('e.g., PO-2025-001')
                                    ->helperText('Purchase order number (optional)'),

                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(fn(Product $record): string => "{$record->product_code} - {$record->name}")
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // Clear qty when product changes to trigger BOM recalculation
                                        $set('qty_produced', null);
                                    })
                                    ->helperText('Select the product to produce'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('qty_produced')
                                    ->label('Quantity to Produce')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->step(0.001)
                                    ->live(debounce: 500)
                                    ->placeholder('e.g., 100')
                                    ->helperText('Number of cartons to produce'),

                                TextInput::make('uom')
                                    ->label('Unit of Measure')
                                    ->required()
                                    ->default('cartons')
                                    ->disabled()
                                    ->helperText('Unit is always cartons'),
                            ]),

                        Textarea::make('notes')
                            ->label('Production Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Additional notes about this production batch'),
                    ])
                    ->columns(1),

                Section::make('BOM Requirements & Stock Validation')
                    ->description('Bill of Materials requirements and current stock availability')
                    ->schema([
                        TextEntry::make('bom_requirements')
                            ->label('Material Requirements')
                            ->html() // enable HTML output
                            ->getStateUsing(function (Get $get): HtmlString {
                                $productId = $get('product_id');
                                $qtyToProduce = $get('qty_produced');

                                if (!$productId || !$qtyToProduce) {
                                    return new HtmlString('<p class="text-gray-500">Select a product and quantity to see material requirements.</p>');
                                }

                                $boms = \App\Models\Bom::where('product_id', $productId)
                                    ->with('packagingItem')
                                    ->get();

                                if ($boms->isEmpty()) {
                                    return new HtmlString('<p class="text-red-600 font-medium">⚠️ No BOM found for this product!</p>');
                                }

                                $html = '<div class="space-y-3">';
                                $html .= '<h4 class="font-medium text-gray-900">Required Materials:</h4>';

                                $allStockSufficient = true;

                                foreach ($boms as $bom) {
                                    $requiredQty = $bom->qty_per_unit * $qtyToProduce;

                                    $currentStock = \App\Models\StockMovement::where('item_type', 'packaging')
                                        ->where('item_id', $bom->packaging_item_id)
                                        ->selectRaw('SUM(CASE WHEN movement_type = "in" THEN qty ELSE -qty END) as current_stock')
                                        ->value('current_stock') ?? 0;

                                    $isStockSufficient = $currentStock >= $requiredQty;
                                    $allStockSufficient = $allStockSufficient && $isStockSufficient;

                                    $statusIcon = $isStockSufficient ? '✅' : '❌';
                                    $statusColor = $isStockSufficient ? 'text-green-600' : 'text-red-600';

                                    $html .= '<div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">';
                                    $html .= '<div>';
                                    $html .= '<span class="font-medium">' . e($bom->packagingItem->name) . '</span>';
                                    $html .= '<br><span class="text-sm text-gray-600">Code: ' . e($bom->packagingItem->packaging_code) . '</span>';
                                    $html .= '</div>';
                                    $html .= '<div class="text-right">';
                                    $html .= '<div class="' . $statusColor . ' font-medium">' . $statusIcon . ' Required: ' . number_format($requiredQty, 3) . ' ' . e($bom->uom) . '</div>';
                                    $html .= '<div class="text-sm text-gray-600">Available: ' . number_format($currentStock, 3) . ' pcs</div>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }

                                if (!$allStockSufficient) {
                                    $html .= '<div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">';
                                    $html .= '<p class="text-red-800 font-medium">⚠️ Insufficient stock for some materials!</p>';
                                    $html .= '<p class="text-red-700 text-sm">Please ensure adequate packaging stock before proceeding.</p>';
                                    $html .= '</div>';
                                } else {
                                    $html .= '<div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">';
                                    $html .= '<p class="text-green-800 font-medium">✅ All materials are available!</p>';
                                    $html .= '<p class="text-green-700 text-sm">Production can proceed.</p>';
                                    $html .= '</div>';
                                }

                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->visible(fn(Get $get): bool => $get('product_id') && $get('qty_produced')),
            ]);
    }
}
