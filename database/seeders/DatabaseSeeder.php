<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run the role and permission seeder first
        $this->call(RolePermissionSeeder::class);

        // Create admin user with role assignment
        $this->call(UserSeeder::class);

        // Seed master data
        $this->call(ProductSeeder::class);
        $this->call(PackagingItemSeeder::class);
        $this->call(BomSeeder::class);
        $this->call(DestinationSeeder::class);

        // User::factory(10)->create();
    }
}
