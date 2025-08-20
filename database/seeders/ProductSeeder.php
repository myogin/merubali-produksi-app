<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'product_code' => 'CCO-CTN50',
                'name' => 'STP-CCO-50',
                'description' => 'Standing Pouch Coconut Chips Original 50gr - Carton 50 pcs',
                'is_active' => true,
            ],
            [
                'product_code' => 'CCO-CTN24',
                'name' => 'STP-CCO-24',
                'description' => 'Standing Pouch Coconut Chips Original 50gr - Carton 24 pcs',
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
