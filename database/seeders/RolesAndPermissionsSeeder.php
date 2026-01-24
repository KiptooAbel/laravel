<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Medicine & Inventory
            'view_medicines',
            'create_medicine',
            'edit_medicine',
            'delete_medicine',
            'manage_inventory',
            'adjust_stock',
            'view_stock_movements',

            // Sales & POS
            'sell_medicine',
            'view_sales',
            'void_sale',
            'apply_discount',

            // Reports
            'view_reports',
            'view_profit_reports',
            'export_reports',

            // Suppliers & Purchases
            'view_suppliers',
            'manage_suppliers',
            'create_purchase',
            'view_purchases',

            // Settings
            'manage_settings',
            'manage_users',
            'manage_roles',

            // Compliance
            'view_audit_logs',
            'handle_controlled_medicines',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Owner - has all permissions
        $owner = Role::create(['name' => 'owner']);
        $owner->givePermissionTo(Permission::all());

        // Pharmacist - manages inventory and can optionally sell
        $pharmacist = Role::create(['name' => 'pharmacist']);
        $pharmacist->givePermissionTo([
            'view_medicines',
            'create_medicine',
            'edit_medicine',
            'manage_inventory',
            'adjust_stock',
            'view_stock_movements',
            'view_suppliers',
            'manage_suppliers',
            'create_purchase',
            'view_purchases',
            'view_reports',
            'handle_controlled_medicines',
            // Note: 'sell_medicine' can be granted dynamically via settings
        ]);

        // Cashier - handles sales only
        $cashier = Role::create(['name' => 'cashier']);
        $cashier->givePermissionTo([
            'view_medicines',
            'sell_medicine',
            'view_sales',
            'apply_discount',
        ]);

        $this->command->info('Roles and permissions created successfully!');
        
        // Create test users for each role
        $ownerUser = \App\Models\User::create([
            'name' => 'Pharmacy Owner',
            'email' => 'owner@chemistpos.com',
            'password' => bcrypt('password'),
        ]);
        $ownerUser->assignRole('owner');
        
        $pharmacistUser = \App\Models\User::create([
            'name' => 'John Pharmacist',
            'email' => 'pharmacist@chemistpos.com',
            'password' => bcrypt('password'),
        ]);
        $pharmacistUser->assignRole('pharmacist');
        
        $cashierUser = \App\Models\User::create([
            'name' => 'Jane Cashier',
            'email' => 'cashier@chemistpos.com',
            'password' => bcrypt('password'),
        ]);
        $cashierUser->assignRole('cashier');
        
        $this->command->info('Test users created:');
        $this->command->info('- owner@chemistpos.com / password (Owner)');
        $this->command->info('- pharmacist@chemistpos.com / password (Pharmacist)');
        $this->command->info('- cashier@chemistpos.com / password (Cashier)');
    }
}
