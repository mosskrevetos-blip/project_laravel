<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $products = Product::all();
        $users = User::whereHas('roles', function ($query) {
            $query->where('slug', 'user');
        })->get();

        // Если нет товаров или пользователей, выходим, чтобы не было ошибки
        if ($products->isEmpty() || $users->isEmpty()) {
            $this->command->info('No products or users to create orders for. Skipping OrderSeeder.');
            return;
        }

        // Создаём 5 случайных заказов
        for ($i = 0; $i < 5; $i++) {
            // Создаём заказ
            $order = Order::create([
                'customer_name' => $faker->name(),
                'customer_email' => $faker->safeEmail(),
                'total_price' => 0, // Посчитаем сумму позже
                'status' => $faker->randomElement(['pending', 'processing', 'shipped', 'completed']),
                'user_id' => $users->random()->id, // Привязываем к случайному пользователю
            ]);

            $totalPrice = 0;
            // Привязываем к заказу от 1 до 3 случайных товаров
            $orderProducts = $products->random(rand(1, 3));

            foreach ($orderProducts as $product) {
                $quantity = rand(1, 2);
                $price = $product->price;
                $totalPrice += $price * $quantity;

                // Привязываем товар к заказу в сводной таблице
                $order->products()->attach($product->id, [
                    'quantity' => $quantity,
                    'price' => $price, // Записываем цену на момент заказа
                ]);
            }

            // Обновляем общую стоимость заказа
            $order->total_price = $totalPrice;
            $order->save();
        }
    }
}