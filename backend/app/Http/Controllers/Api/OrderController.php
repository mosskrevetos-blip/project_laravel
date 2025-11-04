<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Product;


class OrderController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Order::with(['products', 'user']);

        // Админ и менеджер видят всё
        if ($user->hasRole('admin') || $user->hasRole('manager')) {
            return $query->latest()->get();
        }

        // Остальные видят только заказы со своими товарами
        return $query->whereHas('products', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->latest()->get();
    }

    // Методы store, show, update, destroy оставляем для примера
    // В реальности обновление статуса заказа - более сложная логика
    public function show(Order $order)
    {
        return $order->load(['products', 'user']);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,completed,cancelled',
        ]);

        $order->update($validated);

        return $order->load(['products', 'user']);
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(null, 204);
    }

    public function storePublic(Request $request)
    {
        // 1. Валидация данных покупателя и товаров в корзине
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'cart' => 'required|array',
            'cart.*.product_id' => 'required|exists:products,id',
            'cart.*.quantity' => 'required|integer|min:1',
        ]);

        $totalPrice = 0;
        $orderProductsData = [];

        // 2. Проверяем наличие товаров и считаем итоговую сумму
        foreach ($validated['cart'] as $item) {
            $product = Product::find($item['product_id']);
            if (!$product || $product->quantity < $item['quantity']) {
                // Если товара нет или его не хватает на складе
                return response()->json(['message' => 'Товара ' . ($product->title ?? '') . ' не хватает на складе.'], 422);
            }
            $price = $product->price;
            $totalPrice += $price * $item['quantity'];
            $orderProductsData[$product->id] = [
                'quantity' => $item['quantity'],
                'price' => $price,
            ];
        }

        // 3. Создаём заказ и привязываем к нему товары
        $order = DB::transaction(function () use ($validated, $totalPrice, $orderProductsData) {
            $order = Order::create([
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'total_price' => $totalPrice,
                'status' => 'pending',
                'user_id' => null, // Заказ от гостя, user_id пустой
            ]);

            $order->products()->sync($orderProductsData);

            // 4. Уменьшаем количество товаров на складе
            foreach ($orderProductsData as $productId => $data) {
                Product::find($productId)->decrement('quantity', $data['quantity']);
            }
            
            return $order;
        });

        // В реальности здесь бы ещё отправлялось письмо-уведомление

        return response()->json($order->load('products'), 201);
    }
}