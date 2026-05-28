<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FavoriteReportController extends Controller
{
    /**
     * Звіт по обраному для адмін-панелі з урахуванням ролі користувача.
     */
    public function index()
    {
        $user = Auth::user();

        $isAdminOrManager = $user->hasRole('admin') || $user->hasRole('manager');

        $isSellerLike =
            $user->hasRole('seller') ||
            $user->hasRole('wholesale_seller') ||
            $user->hasRole('manufacturer');

        // ------------------------------------------------------------
        // ADMIN / MANAGER:
        // бачать усіх користувачів, усі їх обрані товари і продавців
        // ------------------------------------------------------------
        if ($isAdminOrManager) {
            $users = User::with([
                'roles',
                'favoriteProducts.category',
                'favoriteProducts.user',
                'favoriteSellerUsers.roles',
            ])->get();

            return response()->json([
                'mode' => 'admin',
                'favorite_products_by_user' => $users->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'roles' => $u->roles,
                        'favorite_products' => $u->favoriteProducts,
                    ];
                })->values(),
                'favorite_sellers_by_user' => $users->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'roles' => $u->roles,
                        'favorite_sellers' => $u->favoriteSellerUsers,
                    ];
                })->values(),
                'favorited_my_products' => [],
                'users_who_favorited_me_as_seller' => [],
            ]);
        }

        // ------------------------------------------------------------
        // SELLER / WHOLESALE SELLER / MANUFACTURER:
        // бачать свої обрані товари/продавців +
        // хто додав їх товари в обране +
        // хто додав їх як продавця в обране
        // ------------------------------------------------------------
        if ($isSellerLike) {
            $currentUser = User::with([
                'roles',
                'favoriteProducts.category',
                'favoriteProducts.user',
                'favoriteSellerUsers.roles',
                'products.favoritedByUsers.roles',
                'favoritedByUsersAsSeller.roles',
            ])->findOrFail($user->id);

            $favoritedMyProducts = $currentUser->products->map(function ($product) {
                return [
                    'product' => $product,
                    'users' => $product->favoritedByUsers,
                ];
            })->values();

            return response()->json([
                'mode' => 'seller',
                'favorite_products_by_user' => [[
                    'id' => $currentUser->id,
                    'name' => $currentUser->name,
                    'email' => $currentUser->email,
                    'roles' => $currentUser->roles,
                    'favorite_products' => $currentUser->favoriteProducts,
                ]],
                'favorite_sellers_by_user' => [[
                    'id' => $currentUser->id,
                    'name' => $currentUser->name,
                    'email' => $currentUser->email,
                    'roles' => $currentUser->roles,
                    'favorite_sellers' => $currentUser->favoriteSellerUsers,
                ]],
                'favorited_my_products' => $favoritedMyProducts,
                'users_who_favorited_me_as_seller' => $currentUser->favoritedByUsersAsSeller->values(),
            ]);
        }

        // ------------------------------------------------------------
        // REGULAR USER:
        // бачить тільки свої обрані товари і продавців
        // ------------------------------------------------------------
        $currentUser = User::with([
            'roles',
            'favoriteProducts.category',
            'favoriteProducts.user',
            'favoriteSellerUsers.roles',
        ])->findOrFail($user->id);

        return response()->json([
            'mode' => 'user',
            'favorite_products_by_user' => [[
                'id' => $currentUser->id,
                'name' => $currentUser->name,
                'email' => $currentUser->email,
                'roles' => $currentUser->roles,
                'favorite_products' => $currentUser->favoriteProducts,
            ]],
            'favorite_sellers_by_user' => [[
                'id' => $currentUser->id,
                'name' => $currentUser->name,
                'email' => $currentUser->email,
                'roles' => $currentUser->roles,
                'favorite_sellers' => $currentUser->favoriteSellerUsers,
            ]],
            'favorited_my_products' => [],
            'users_who_favorited_me_as_seller' => [],
        ]);
    }
}