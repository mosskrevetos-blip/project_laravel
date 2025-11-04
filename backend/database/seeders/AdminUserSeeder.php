<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Создаём роль администратора, если её ещё нет
            $adminRole = Role::firstOrCreate(
                ['slug' => 'admin'],
                ['name' => 'Administrator']
            );

            // 2. Создаём пользователя-администратора, если его ещё нет
            $adminUser = User::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Admin',
                    'password' => Hash::make('password'), // ВНИМАНИЕ: смените на сложный пароль
                    'email_verified_at' => now(),
                ]
            );

            // 3. Привязываем роль к пользователю, если она ещё не привязана
            if (!$adminUser->roles()->where('slug', 'admin')->exists()) {
                $adminUser->roles()->attach($adminRole);
            }
        });
    }
}