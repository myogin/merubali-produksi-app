<?php

namespace Database\Seeders;

use App\Models\PackagingItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackagingItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packagingItems = [
            [
                'packaging_code' => 'STP-CCO-50',
                'name' => 'Standing Pouch Coconut Chips Original 50gr',
                'base_uom' => 'pcs',
                'description' => 'Standing pouch packaging for coconut chips 50gr',
                'is_active' => true,
            ],
            [
                'packaging_code' => 'CTN50',
                'name' => 'Karton Box 50 pcs',
                'base_uom' => 'pcs',
                'description' => 'Carton box for 50 pieces',
                'is_active' => true,
            ],
            [
                'packaging_code' => 'CTN24',
                'name' => 'Karton Box 24 pcs',
                'base_uom' => 'pcs',
                'description' => 'Carton box for 24 pieces',
                'is_active' => true,
            ],
        ];

        foreach ($packagingItems as $packagingItem) {
            PackagingItem::create($packagingItem);
        }
    }
}
