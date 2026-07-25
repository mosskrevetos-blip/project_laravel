<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Повертаємо всіх користувачів з їхніми ролями
        return User::with('roles')->latest()->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
            'roles' => ['sometimes', 'array']
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        return response()->json($user->load('roles'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return $user->load('roles');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class. ',email,'.$user->id],
            'password' => ['nullable', Rules\Password::defaults()],
            'roles' => ['sometimes', 'array']
        ]);

        $user->update($request->except('password'));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        return response()->json($user->load('roles'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Не дозволяємо користувачеві видалити себе
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Ви не можете видалити себе.'], 403);
        }
        
        $user->delete();

        return response()->json(null, 204);
    }


    public function publicSellerProfile(Request $request, User $seller): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 12);
        if ($perPage <= 0) $perPage = 12;

        $productsQuery = Product::query()
            ->with(['category', 'user'])
            ->where('user_id', $seller->id)
            ->publishedForSearch()
            ->withAvg(['reviews as rating_avg' => function ($q) {
                $q->where('moderation_status', 'approved');
            }], 'rating')
            ->withCount(['reviews as reviews_count' => function ($q) {
                $q->where('moderation_status', 'approved');
            }])
            ->latest();

        $products = $productsQuery->paginate($perPage);

        $sellerData = [
            'id' => $seller->id,
            'name' => $seller->name,
            'seller_rating' => (int) ($seller->seller_rating ?? 0),
            'buyer_rating' => (int) ($seller->buyer_rating ?? 0),
            'wholesale_seller_rating' => (int) ($seller->wholesale_seller_rating ?? 0),
            'manufacturer_rating' => (int) ($seller->manufacturer_rating ?? 0),
            'last_seen_at' => $seller->last_seen_at,
        ];

        return response()->json([
            'seller' => $sellerData,
            'products' => [
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'links' => [
                    'first' => $products->url(1),
                    'last' => $products->url($products->lastPage()),
                    'prev' => $products->previousPageUrl(),
                    'next' => $products->nextPageUrl(),
                ],
            ],
        ]);
    }
}
