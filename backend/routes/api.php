<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FavoriteSellerController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PresenceController;

//==========================================================================
// ПУБЛІЧНІ МАРШРУТИ (доступні всім)
//==========================================================================

Route::post('/orders/public', [OrderController::class, 'storePublic']);

// CHANGED: expose delivery and payment methods for frontend
Route::get('/delivery-methods', [OrderController::class, 'deliveryMethods']);
Route::get('/payment-methods', [OrderController::class, 'paymentMethods']);

// --- Товари ---
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/popular', [ProductController::class, 'popular']);
Route::get('/products/newest', [ProductController::class, 'newest']);
Route::get('/products/{product}-{slug?}', [ProductController::class, 'show'])
    ->where('product', '[0-9]+')
    ->where('slug', '[a-z0-9\-]+')
    ->middleware('can:view,product');

// Отримання атрибутів для конкретної категорії
Route::get('/categories/{category}/attributes', [CategoryController::class, 'getAttributes']);
Route::get('/categories/suggest', [CategoryController::class, 'suggest']); 
Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('can:view,category');

// --- Категорії ---
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('can:view,category');


//==========================================================================
// ЗАХИЩЕНІ МАРШРУТИ (вимагають аутентифікації та відповідних прав доступу)
//==========================================================================

Route::middleware('auth:sanctum')->group(function () {
    
    // --- Поточний користувач ---
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles');
    });
    
    // --- Ролі ---
    Route::get('/roles', [RoleController::class, 'index']);

    // --- Кошик ---
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);
    Route::post('/cart/sync', [CartController::class, 'sync']);

    // --- Товари (управління) ---
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:create,App\Models\Product');
    Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->middleware('can:update,product');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('can:delete,product');

    // --- Категорії (управління) ---
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('can:create,App\Models\Category');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('can:update,category');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('can:delete,category');

    // --- Користувачі (управління) ---
    Route::get('/users', [UserController::class, 'index'])->middleware('can:viewAny,App\Models\User');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:create,App\Models\User');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('can:view,user');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:update,user');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('can:delete,user');

    // --- Замовлення (управління) ---
    Route::get('/orders', [OrderController::class, 'index'])->middleware('can:viewAny,App\Models\Order');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('can:view,order');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->middleware('can:update,order');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->middleware('can:delete,order');

    // --- Атрибути (управління) ---
    Route::get('/attributes', [AttributeController::class, 'index'])->middleware('can:viewAny,App\Models\Attribute');
    Route::post('/attributes', [AttributeController::class, 'store'])->middleware('can:create,App\Models\Attribute');
    Route::get('/attributes/{attribute}', [AttributeController::class, 'show'])->middleware('can:view,attribute');
    Route::put('/attributes/{attribute}', [AttributeController::class, 'update'])->middleware('can:update,attribute');
    Route::delete('/attributes/{attribute}', [AttributeController::class, 'destroy'])->middleware('can:delete,attribute');

    // Обрані товари
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{productId}', [FavoriteController::class, 'destroy']);
    Route::post('/favorites/sync', [FavoriteController::class, 'sync']);
    Route::get('/favorites/check/{productId}', [FavoriteController::class, 'check']);

    // Обрані продавці
    Route::get('/favorite-sellers', [FavoriteSellerController::class, 'index']);
    Route::post('/favorite-sellers', [FavoriteSellerController::class, 'store']);
    Route::delete('/favorite-sellers/{sellerId}', [FavoriteSellerController::class, 'destroy']);
    Route::get('/favorite-sellers/check/{sellerId}', [FavoriteSellerController::class, 'check']);

    // процес присутності користувача (для відображення онлайн-статусу)
    Route::post('/presence/ping', [PresenceController::class, 'ping']);

    // Конверсції та повідомлення
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/with-seller/{seller}', [ConversationController::class, 'withSeller']);

    // Повідомлення
    Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
    Route::post('/conversations/{conversation}/read', [MessageController::class, 'markRead']);

});

//==========================================================================
// МАРШРУТИ АУТЕНТИФІКАЦІЇ (login, register, logout...)
//==========================================================================

require __DIR__.'/auth.php';