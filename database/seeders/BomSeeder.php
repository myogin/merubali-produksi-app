<?php

namespace Database\Seeders;

use App\Models\Bom;
use App\Models\Product;
use App\Models\PackagingItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get products and packaging items by their codes
        $productCCO50 = Product::where('product_code', 'CCO-CTN50')->first();
        $productCCO24 = Product::where('product_code', 'CCO-CTN24')->first();

        $packagingSTP = PackagingItem::where('packaging_code', 'STP-CCO-50')->first();
        $packagingCTN50 = PackagingItem::where('packaging_code', 'CTN50')->first();
        $packagingCTN24 = PackagingItem::where('packaging_code', 'CTN24')->first();

        $boms = [
            [
                'product_id' => $productCCO50->id,
                'packaging_item_id' => $packagingSTP->id,
                'qty_per_unit' => 50,
                'uom' => 'pcs',
            ],
            [
                'product_id' => $productCCO50->id,
                'packaging_item_id' => $packagingCTN50->id,
                'qty_per_unit' => 1,
                'uom' => 'pcs',
            ],
            [
                'product_id' => $productCCO24->id,
                'packaging_item_id' => $packagingSTP->id,
                'qty_per_unit' => 24,
                'uom' => 'pcs',
            ],
            [
                'product_id' => $productCCO24->id,
                'packaging_item_id' => $packagingCTN24->id,
                'qty_per_unit' => 1,
                'uom' => 'pcs',
            ],
        ];

        foreach ($boms as $bom) {
            Bom::create($bom);
        }
    }
}
