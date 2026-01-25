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

        // Define the guard name
        $guardName = 'sanctum';

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
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => $guardName]
            );
        }

        // Create roles and assign permissions

        // Owner - has all permissions
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => $guardName]);
        $owner->syncPermissions(Permission::where('guard_name', $guardName)->get());

        // Pharmacist - manages inventory and can optionally sell
        $pharmacist = Role::firstOrCreate(['name' => 'pharmacist', 'guard_name' => $guardName]);
        $pharmacist->syncPermissions([
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
        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => $guardName]);
        $cashier->syncPermissions([
            'view_medicines',
            'sell_medicine',
            'view_sales',
            'apply_discount',
        ]);

        $this->command->info('Roles and permissions created successfully!');
        
        // Create test users for each role
        $ownerUser = \App\Models\User::firstOrCreate(
            ['email' => 'owner@chemistpos.com'],
            [
                'name' => 'Pharmacy Owner',
                'password' => bcrypt('password'),
            ]
        );
        $ownerUser->syncRoles([$owner]);
        
        $pharmacistUser = \App\Models\User::firstOrCreate(
            ['email' => 'pharmacist@chemistpos.com'],
            [
                'name' => 'John Pharmacist',
                'password' => bcrypt('password'),
            ]
        );
        $pharmacistUser->syncRoles([$pharmacist]);
        
        $cashierUser = \App\Models\User::firstOrCreate(
            ['email' => 'cashier@chemistpos.com'],
            [
                'name' => 'Jane Cashier',
                'password' => bcrypt('password'),
            ]
        );
        $cashierUser->syncRoles([$cashier]);
        
        $this->command->info('Test users created:');
        $this->command->info('- owner@chemistpos.com / password (Owner)');
        $this->command->info('- pharmacist@chemistpos.com / password (Pharmacist)');
        $this->command->info('- cashier@chemistpos.com / password (Cashier)');
    }
}
