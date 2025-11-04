<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Вызываем сидеры в правильном порядке:
        // Сначала должны быть созданы роли, потом пользователи.
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            TestUserSeeder::class,
            AttributeSeeder::class,
            OrderSeeder::class,
        ]);
    }
}