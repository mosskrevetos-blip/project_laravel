<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    //Отримати кошик поточного користувача
    public function index()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['items' => []]);
        }

        Log::info('Завантаження кошика для користувача:', ['user_id' => $user->id]);

        try {
            $items = Cart::where('user_id', $user->id)
                ->with('product')
                ->get()
                ->filter(function ($item) {
                    if (!$item->product) {
                        Log::warning('Товар не знайдено для запису кошика:', [
                            'cart_id' => $item->id, 
                            'product_id' => $item->product_id
                        ]);
                        $item->delete();
                        return false;
                    }
                    return true;
                })
                ->values();

            Log::info('Кошик успішно завантажено:', ['items_count' => $items->count()]);

            return response()->json(['items' => $items]);
        } catch (\Throwable $e) {
            Log::error('Помилка завантаження кошика:', [
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }


    // Додати товар у кошик
    public function store(Request $request)
    {
        // Валідація вхідних даних
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        if (!$user) {
            // Гості не можуть додавати товари на сервер — вони використовують localStorage
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Знаходимо товар
        $product = Product::findOrFail($validated['product_id']);

        // Перевіряємо наявність товару на складі
        if ($product->quantity < $validated['quantity']) {
            return response()->json(['message' => 'Недостатньо товару на складі'], 422);
        }

        // Створюємо або оновлюємо запис у кошику
        // updateOrCreate — якщо товар вже є в кошику, збільшуємо кількість, інакше створюємо новий запис
        $cart = Cart::updateOrCreate(
            [
                'user_id' => $user->id,
                'product_id' => $validated['product_id'],
            ],
            [
                'quantity' => $validated['quantity'],
                'price' => $product->price,
            ]
        );

        // Повертаємо оновлений запис кошика з підвантаженням товару
        return response()->json(['cart' => $cart->load('product')], 201);
    }


    // Оновити кількість товару в кошику
    public function update(Request $request, $id)
    {
        // Валідація вхідних даних
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Знаходимо запис кошика, який належить поточному користувачу
        $cart = Cart::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Перевіряємо наявність товару на складі
        if ($cart->product->quantity < $validated['quantity']) {
            return response()->json(['message' => 'Недостатньо товару на складі'], 422);
        }

        // Оновлюємо кількість
        $cart->update(['quantity' => $validated['quantity']]);

        // Повертаємо оновлений запис з підвантаженням товару
        return response()->json(['cart' => $cart->load('product')]);
    }


    // Видалити товар з кошика
    public function destroy($id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Знаходимо запис кошика, який належить поточному користувачу
        $cart = Cart::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Видаляємо запис
        $cart->delete();

        return response()->json(['message' => 'Товар видалено з кошика'], 200);
    }


    // Синхронізація кошика при авторизації
    // Переносить товари з localStorage (фронтенду) у БД
    public function sync(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $items = $request->input('items', []);

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            
            if (!$product) {
                continue; // Пропускаємо неіснуючі товари
            }

            // Знаходимо існуючий запис у кошику
            $existingCart = Cart::where('user_id', $user->id)
                ->where('product_id', $item['product_id'])
                ->first();

            if ($existingCart) {
                // ✅ Якщо товар вже є в БД — ДОДАЄМО кількість
                $newQuantity = $existingCart->quantity + $item['quantity'];
                
                // Перевіряємо, щоб не перевищити наявність на складі
                if ($newQuantity > $product->quantity) {
                    $newQuantity = $product->quantity; // Обмежуємо максимальною кількістю
                }

                $existingCart->update([
                    'quantity' => $newQuantity,
                    'price' => $product->price, // Оновлюємо ціну (на випадок зміни)
                ]);
            } else {
                // ✅ Якщо товару немає в БД — створюємо новий запис
                Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ]);
            }
        }

        // Повертаємо оновлений кошик з сервера
        $carts = Cart::where('user_id', $user->id)
            ->with('product')
            ->get()
            ->filter(function ($item) {
                // Видаляємо товари, які було видалено з БД
                if (!$item->product) {
                    $item->delete();
                    return false;
                }
                return true;
            })
            ->values();

        return response()->json(['items' => $carts]);
    }
}