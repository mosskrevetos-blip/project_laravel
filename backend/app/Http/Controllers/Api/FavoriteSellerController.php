<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FavoriteSeller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteSellerController extends Controller
{
    /** Отримати список обраних продавців поточного користувача */
    public function index()
    {
        $sellers = Auth::user()
            ->favoriteSellerUsers()
            ->get();

        return response()->json($sellers);
    }

    /**
     * Додати продавця до обраного
     */
    public function store(Request $request)
    {
        $request->validate([
            'seller_id' => 'required|exists:users,id',
        ]);

        // Перевірка: не можна додати самого себе
        if ($request->seller_id == Auth::id()) {
            return response()->json(['message' => 'Не можна додати себе в обране'], 400);
        }

        $favoriteSeller = FavoriteSeller::firstOrCreate([
            'user_id' => Auth::id(),
            'seller_id' => $request->seller_id,
        ]);

        return response()->json([
            'message' => 'Продавця додано в обране',
            'favorite_seller' => $favoriteSeller,
        ], 201);
    }

    /**
    * Синхронізувати список обраних продавців
    * Приймає масив seller_ids і оновлює список обраного відповідно до нього
    */
    public function sync(Request $request)
    {
        $request->validate([
            'seller_ids' => 'required|array',
            'seller_ids.*' => 'exists:users,id',
        ]);

        $userId = Auth::id();

        foreach ($request->seller_ids as $sellerId) {
            // Не даём добавить самого себя
            if ((int) $sellerId === (int) $userId) {
                continue;
            }

            FavoriteSeller::firstOrCreate([
                'user_id' => $userId,
                'seller_id' => $sellerId,
            ]);
        }

        return response()->json([
            'message' => 'Обраних продавців синхронізовано'
        ]);
    }

    /**
     * Видалити продавця з обраного
     */
    public function destroy($sellerId)
    {
        $deleted = FavoriteSeller::where('user_id', Auth::id())
            ->where('seller_id', $sellerId)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Продавця видалено з обраного']);
        }

        return response()->json(['message' => 'Продавця не знайдено в обраному'], 404);
    }

    /**
     * Перевірити, чи знаходиться продавець в обраному
     */
    public function check($sellerId)
    {
        $isFavorite = FavoriteSeller::where('user_id', Auth::id())
            ->where('seller_id', $sellerId)
            ->exists();

        return response()->json(['is_favorite' => $isFavorite]);
    }
}