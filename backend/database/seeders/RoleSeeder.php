<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // firstOrCreate - не створює дублікати при наступному створенні
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        Role::firstOrCreate(['slug' => 'manager'], ['name' => 'Manager']);
        Role::firstOrCreate(['slug' => 'seller'], ['name' => 'Seller']);
        Role::firstOrCreate(['slug' => 'wholesaler'], ['name' => 'Wholesaler']);
        Role::firstOrCreate(['slug' => 'manufacturer'], ['name' => 'Manufacturer']);
        Role::firstOrCreate(['slug' => 'user'], ['name' => 'User']);
        Role::firstOrCreate(['slug' => 'guest'], ['name' => 'Guest']);
    }
}
