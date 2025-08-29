<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $this->createPermissions();

        // Create roles and assign permissions
        $this->createRoles();
    }

    private function createPermissions(): void
    {
        $permissions = [
            // Navigation permissions
            'view_master_data_navigation',
            'view_transactions_navigation',

            // User permissions
            'view_any_users::user',
            'view_users::user',
            'create_users::user',
            'update_users::user',
            'delete_users::user',
            'delete_any_users::user',
            'force_delete_users::user',
            'force_delete_any_users::user',
            'restore_users::user',
            'restore_any_users::user',
            'replicate_users::user',
            'reorder_users::user',

            // Role permissions
            'view_any_role',
            'view_role',
            'create_role',
            'update_role',
            'delete_role',
            'delete_any_role',
            'force_delete_role',
            'force_delete_any_role',
            'restore_role',
            'restore_any_role',
            'replicate_role',
            'reorder_role',

            // Product permissions (Master Data)
            'view_any_product',
            'view_product',
            'create_product',
            'update_product',
            'delete_product',
            'delete_any_product',
            'force_delete_product',
            'force_delete_any_product',
            'restore_product',
            'restore_any_product',
            'replicate_product',
            'reorder_product',

            // Packaging Item permissions (Master Data)
            'view_any_packaging::item',
            'view_packaging::item',
            'create_packaging::item',
            'update_packaging::item',
            'delete_packaging::item',
            'delete_any_packaging::item',
            'force_delete_packaging::item',
            'force_delete_any_packaging::item',
            'restore_packaging::item',
            'restore_any_packaging::item',
            'replicate_packaging::item',
            'reorder_packaging::item',

            // BOM permissions (Master Data)
            'view_any_bom',
            'view_bom',
            'create_bom',
            'update_bom',
            'delete_bom',
            'delete_any_bom',
            'force_delete_bom',
            'force_delete_any_bom',
            'restore_bom',
            'restore_any_bom',
            'replicate_bom',
            'reorder_bom',

            // Destination permissions (Master Data)
            'view_any_destination',
            'view_destination',
            'create_destination',
            'update_destination',
            'delete_destination',
            'delete_any_destination',
            'force_delete_destination',
            'force_delete_any_destination',
            'restore_destination',
            'restore_any_destination',
            'replicate_destination',
            'reorder_destination',

            // Production Batch permissions (Transactions)
            'view_any_production::batch',
            'view_production::batch',
            'create_production::batch',
            'update_production::batch',
            'delete_production::batch',
            'delete_any_production::batch',
            'force_delete_production::batch',
            'force_delete_any_production::batch',
            'restore_production::batch',
            'restore_any_production::batch',
            'replicate_production::batch',
            'reorder_production::batch',

            // Receipt permissions (Transactions)
            'view_any_receipt',
            'view_receipt',
            'create_receipt',
            'update_receipt',
            'delete_receipt',
            'delete_any_receipt',
            'force_delete_receipt',
            'force_delete_any_receipt',
            'restore_receipt',
            'restore_any_receipt',
            'replicate_receipt',
            'reorder_receipt',

            // Shipment permissions (Transactions)
            'view_any_shipment',
            'view_shipment',
            'create_shipment',
            'update_shipment',
            'delete_shipment',
            'delete_any_shipment',
            'force_delete_shipment',
            'force_delete_any_shipment',
            'restore_shipment',
            'restore_any_shipment',
            'replicate_shipment',
            'reorder_shipment',

            // Stock Movement permissions (Transactions)
            'view_any_stock::movement',
            'view_stock::movement',
            'create_stock::movement',
            'update_stock::movement',
            'delete_stock::movement',
            'delete_any_stock::movement',
            'force_delete_stock::movement',
            'force_delete_any_stock::movement',
            'restore_stock::movement',
            'restore_any_stock::movement',
            'replicate_stock::movement',
            'reorder_stock::movement',

            // Activity Log permissions
            'view_any_activity',
            'view_activity',
            'create_activity',
            'update_activity',
            'delete_activity',
            'delete_any_activity',
            'force_delete_activity',
            'force_delete_any_activity',
            'restore_activity',
            'restore_any_activity',
            'replicate_activity',
            'reorder_activity',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    private function createRoles(): void
    {
        // Create Admin role with all permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Create Admin-Staf role with master data and transactions access
        $adminStafRole = Role::firstOrCreate(['name' => 'admin-staf']);
        $adminStafPermissions = [
            // Navigation
            'view_master_data_navigation',
            'view_transactions_navigation',

            // Master Data permissions
            'view_any_product',
            'view_product',
            'create_product',
            'update_product',
            'delete_product',
            'delete_any_product',
            'force_delete_product',
            'force_delete_any_product',
            'restore_product',
            'restore_any_product',
            'replicate_product',
            'reorder_product',

            'view_any_packaging::item',
            'view_packaging::item',
            'create_packaging::item',
            'update_packaging::item',
            'delete_packaging::item',
            'delete_any_packaging::item',
            'force_delete_packaging::item',
            'force_delete_any_packaging::item',
            'restore_packaging::item',
            'restore_any_packaging::item',
            'replicate_packaging::item',
            'reorder_packaging::item',

            'view_any_bom',
            'view_bom',
            'create_bom',
            'update_bom',
            'delete_bom',
            'delete_any_bom',
            'force_delete_bom',
            'force_delete_any_bom',
            'restore_bom',
            'restore_any_bom',
            'replicate_bom',
            'reorder_bom',

            'view_any_destination',
            'view_destination',
            'create_destination',
            'update_destination',
            'delete_destination',
            'delete_any_destination',
            'force_delete_destination',
            'force_delete_any_destination',
            'restore_destination',
            'restore_any_destination',
            'replicate_destination',
            'reorder_destination',

            // Transaction permissions
            'view_any_production::batch',
            'view_production::batch',
            'create_production::batch',
            'update_production::batch',
            'delete_production::batch',
            'delete_any_production::batch',
            'force_delete_production::batch',
            'force_delete_any_production::batch',
            'restore_production::batch',
            'restore_any_production::batch',
            'replicate_production::batch',
            'reorder_production::batch',

            'view_any_receipt',
            'view_receipt',
            'create_receipt',
            'update_receipt',
            'delete_receipt',
            'delete_any_receipt',
            'force_delete_receipt',
            'force_delete_any_receipt',
            'restore_receipt',
            'restore_any_receipt',
            'replicate_receipt',
            'reorder_receipt',

            'view_any_shipment',
            'view_shipment',
            'create_shipment',
            'update_shipment',
            'delete_shipment',
            'delete_any_shipment',
            'force_delete_shipment',
            'force_delete_any_shipment',
            'restore_shipment',
            'restore_any_shipment',
            'replicate_shipment',
            'reorder_shipment',

            'view_any_stock::movement',
            'view_stock::movement',
            'create_stock::movement',
            'update_stock::movement',
            'delete_stock::movement',
            'delete_any_stock::movement',
            'force_delete_stock::movement',
            'force_delete_any_stock::movement',
            'restore_stock::movement',
            'restore_any_stock::movement',
            'replicate_stock::movement',
            'reorder_stock::movement',
        ];
        $adminStafRole->givePermissionTo($adminStafPermissions);

        // Create Staf role with transactions access only
        $stafRole = Role::firstOrCreate(['name' => 'staf']);
        $stafPermissions = [
            // Navigation
            'view_transactions_navigation',

            // Transaction permissions only
            'view_any_production::batch',
            'view_production::batch',
            'create_production::batch',
            'update_production::batch',
            'delete_production::batch',
            'delete_any_production::batch',
            'force_delete_production::batch',
            'force_delete_any_production::batch',
            'restore_production::batch',
            'restore_any_production::batch',
            'replicate_production::batch',
            'reorder_production::batch',

            'view_any_receipt',
            'view_receipt',
            'create_receipt',
            'update_receipt',
            'delete_receipt',
            'delete_any_receipt',
            'force_delete_receipt',
            'force_delete_any_receipt',
            'restore_receipt',
            'restore_any_receipt',
            'replicate_receipt',
            'reorder_receipt',

            'view_any_shipment',
            'view_shipment',
            'create_shipment',
            'update_shipment',
            'delete_shipment',
            'delete_any_shipment',
            'force_delete_shipment',
            'force_delete_any_shipment',
            'restore_shipment',
            'restore_any_shipment',
            'replicate_shipment',
            'reorder_shipment',

            'view_any_stock::movement',
            'view_stock::movement',
            'create_stock::movement',
            'update_stock::movement',
            'delete_stock::movement',
            'delete_any_stock::movement',
            'force_delete_stock::movement',
            'force_delete_any_stock::movement',
            'restore_stock::movement',
            'restore_any_stock::movement',
            'replicate_stock::movement',
            'reorder_stock::movement',
        ];
        $stafRole->givePermissionTo($stafPermissions);

        $this->command->info('Roles and permissions created successfully!');
        $this->command->info('Created roles: admin, admin-staf, staf');
        $this->command->info('Total permissions created: ' . Permission::count());
    }
}
