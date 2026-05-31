<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\FavoriteProductEvent;
use App\Models\Product;
use App\Models\User;
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
     * Видалити товар з обраного поточного користувача
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
     * Видалити товар з обраного конкретного користувача.
     * Доступно лише адміну та менеджеру.
     */
    public function destroyForUser(User $user, Product $product)
    {
        $authUser = Auth::user();

        if (!$authUser->hasRole('admin') && !$authUser->hasRole('manager')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $deleted = Favorite::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Товар не знайдено в обраному користувача'
            ], 404);
        }

        FavoriteProductEvent::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'event_type' => 'remove',
            'source' => $authUser->hasRole('admin') ? 'admin_panel' : 'manager_panel',
            'created_by' => $authUser->id,
        ]);

        return response()->json([
            'message' => 'Товар видалено з обраного користувача успішно.'
        ]);
    }

    /**
     * Повернути список подій по обраному для поточного користувача
     */
    public function events(Request $request)
    {
        $request->validate([
            'since' => 'nullable|date',
        ]);

        $query = FavoriteProductEvent::query()
            ->where('user_id', Auth::id())
            ->orderBy('created_at');

        if ($request->filled('since')) {
            $query->where('created_at', '>', $request->input('since'));
        }

        $events = $query->get([
            'id',
            'product_id',
            'event_type',
            'source',
            'created_at',
        ]);

        return response()->json($events);
    }

    /**
     * Повна синхронізація обраного.
     * localStorage вважається джерелом істини.
     */
    public function sync(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $user = Auth::user();

        $productIds = collect($request->product_ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $user->favoriteProducts()->sync($productIds);

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