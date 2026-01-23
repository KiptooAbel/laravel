<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        // Create default owner user
        $owner = User::factory()->create([
            'name' => 'Pharmacy Owner',
            'email' => 'owner@chemistpos.com',
            'password' => bcrypt('password'),
        ]);
        $owner->assignRole('owner');

        $this->command->info('Default owner created: owner@chemistpos.com / password');
    }
}
