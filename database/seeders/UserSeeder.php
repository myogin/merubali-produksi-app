<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'yoginugraha19@gmail.com'],
            [
                'name' => 'Admin User',
                'email' => 'yoginugraha19@gmail.com',
                'password' => Hash::make('supersekali'),
                'email_verified_at' => now(),
            ]
        );

        // Assign admin role to the user
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminUser->assignRole($adminRole);
            $this->command->info('Admin user created and assigned admin role successfully!');
            $this->command->info('Email: yoginugraha19@gmail.com');
            $this->command->info('Password: supersekali');
        } else {
            $this->command->error('Admin role not found. Please run RolePermissionSeeder first.');
        }
    }
}
