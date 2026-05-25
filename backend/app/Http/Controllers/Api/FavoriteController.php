<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /** Отримати список обраних товарів поточного користувача */
    public function index()
    {
        $favorites = Auth::user()
            ->favoriteProducts()
            ->with('category')
            ->get();

        return response()->json($favorites);
    }

    /**
     * Додати товар до обраного
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $favorite = Favorite::firstOrCreate([
            'user_id' => Auth::id(),
            'product_id' => $request->product_id,
        ]);

        return response()->json([
            'message' => 'Товар додано в обране',
            'favorite' => $favorite,
        ], 201);
    }

    /**
     * Видалити товар з обраного
     */
    public function destroy($productId)
    {
        $deleted = Favorite::where('user_id', Auth::id())
            ->where('product_id', $productId)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'Товар видалено з обраного']);
        }

        return response()->json(['message' => 'Товар не знайдено в обраному'], 404);
    }

    /**
     * Синхронізація обраного (для гостей після авторизації)
     */
    public function sync(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $userId = Auth::id();

        foreach ($request->product_ids as $productId) {
            Favorite::firstOrCreate([
                'user_id' => $userId,
                'product_id' => $productId,
            ]);
        }

        return response()->json(['message' => 'Обране синхронізовано']);
    }

    /**
     * Перевірити, чи знаходиться товар в обраному
     */
    public function check($productId)
    {
        $isFavorite = Favorite::where('user_id', Auth::id())
            ->where('product_id', $productId)
            ->exists();

        return response()->json(['is_favorite' => $isFavorite]);
    }
}