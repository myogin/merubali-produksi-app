<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $destinations = [
            ['name' => 'Jakarta, Indonesia', 'is_active' => true],
            ['name' => 'Surabaya, Indonesia', 'is_active' => true],
            ['name' => 'Bandung, Indonesia', 'is_active' => true],
            ['name' => 'Medan, Indonesia', 'is_active' => true],
            ['name' => 'Semarang, Indonesia', 'is_active' => true],
            ['name' => 'Makassar, Indonesia', 'is_active' => true],
            ['name' => 'Palembang, Indonesia', 'is_active' => true],
            ['name' => 'Tangerang, Indonesia', 'is_active' => true],
            ['name' => 'Depok, Indonesia', 'is_active' => true],
            ['name' => 'Bekasi, Indonesia', 'is_active' => true],
        ];

        foreach ($destinations as $destination) {
            Destination::create($destination);
        }
    }
}
