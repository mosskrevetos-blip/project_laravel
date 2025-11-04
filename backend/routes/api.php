<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AttributeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

//==========================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (доступны всем)
//==========================================================================

Route::post('/orders/public', [OrderController::class, 'storePublic']);

// --- Товары ---
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/popular', [ProductController::class, 'popular']);
Route::get('/products/newest', [ProductController::class, 'newest']);
Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('can:view,product');

// Получение атрибутов для конкретной категории
Route::get('/categories/{category}/attributes', [CategoryController::class, 'getAttributes']);
Route::get('/categories/suggest', [CategoryController::class, 'suggest']); 
Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('can:view,category');

// --- Категории ---
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('can:view,category');


//==========================================================================
// ЗАЩИЩЁННЫЕ МАРШРУТЫ (требуют аутентификации)
//==========================================================================

Route::middleware('auth:sanctum')->group(function () {
    
    // --- Текущий пользователь ---
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles');
    });
    
    // --- Роли ---
    Route::get('/roles', [RoleController::class, 'index']);

    // --- Товары (управление) ---
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:create,App\Models\Product');
    Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->middleware('can:update,product');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('can:delete,product');

    // --- Категории (управление) ---
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('can:create,App\Models\Category');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('can:update,category');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('can:delete,category');

    // --- Пользователи (управление) ---
    Route::get('/users', [UserController::class, 'index'])->middleware('can:viewAny,App\Models\User');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:create,App\Models\User');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('can:view,user');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:update,user');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('can:delete,user');

    // --- Заказы (управление) ---
    Route::get('/orders', [OrderController::class, 'index'])->middleware('can:viewAny,App\Models\Order');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('can:view,order');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->middleware('can:update,order');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->middleware('can:delete,order');

    // --- Атрибуты (управление) ---
    Route::get('/attributes', [AttributeController::class, 'index'])->middleware('can:viewAny,App\Models\Attribute');
    Route::post('/attributes', [AttributeController::class, 'store'])->middleware('can:create,App\Models\Attribute');
    Route::get('/attributes/{attribute}', [AttributeController::class, 'show'])->middleware('can:view,attribute');
    Route::put('/attributes/{attribute}', [AttributeController::class, 'update'])->middleware('can:update,attribute');
    Route::delete('/attributes/{attribute}', [AttributeController::class, 'destroy'])->middleware('can:delete,attribute');
});


//==========================================================================
// МАРШРУТЫ АУТЕНТИФИКАЦИИ (login, register, logout...)
//==========================================================================

require __DIR__.'/auth.php';