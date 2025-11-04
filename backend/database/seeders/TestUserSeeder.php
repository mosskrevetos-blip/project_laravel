<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Manager Test', 'email' => 'manager@example.com', 'role' => 'manager'],
            ['name' => 'User Test', 'email' => 'user@example.com', 'role' => 'user'],
            ['name' => 'Wholesaler Test', 'email' => 'wholesaler@example.com', 'role' => 'wholesaler'],
            ['name' => 'Manufacturer Test', 'email' => 'manufacturer@example.com', 'role' => 'manufacturer'],
        ];

        foreach ($users as $userData) {
            // Находим роль в базе данных
            $role = Role::where('slug', $userData['role'])->first();

            // Создаём пользователя, если его ещё нет
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'), // У всех тестовых пользователей будет пароль 'password'
                    'email_verified_at' => now(),
                ]
            );

            // Привязываем роль к пользователю
            if ($user && $role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }
    }
}