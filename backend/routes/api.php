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
use App\Http\Controllers\Api\FavoriteReportController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ConversationReportController;
use App\Http\Controllers\Api\AdminMessageController;
use App\Http\Controllers\Api\ProductCommentController;
use App\Http\Controllers\Api\ProductCommentReactionController;
use App\Http\Controllers\Api\ProductCommentReportController;
use App\Http\Controllers\Api\AdminProductCommentController;
use App\Http\Controllers\Api\AdminProductCommentReportController;


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

// Пошук товарів
Route::get('/search/products', [SearchController::class, 'products']);
Route::get('/search/suggestions', [SearchController::class, 'suggestions']);

// Коментарі товару (публічний список, з урахуванням видимості)
Route::get('/products/{product}/comments', [ProductCommentController::class, 'index']);


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
    Route::delete('/favorites/users/{user}/products/{product}', [FavoriteController::class, 'destroyForUser']);
    Route::post('/favorites/sync', [FavoriteController::class, 'sync']);
    Route::get('/favorites/check/{productId}', [FavoriteController::class, 'check']);

    // Звіт по обраному для адмін-панелі
    Route::get('/favorites/report', [FavoriteReportController::class, 'index']);

    // Обрані продавці
    Route::get('/favorite-sellers', [FavoriteSellerController::class, 'index']);
    Route::post('/favorite-sellers', [FavoriteSellerController::class, 'store']);
    Route::delete('/favorite-sellers/{sellerId}', [FavoriteSellerController::class, 'destroy']);
    Route::delete('/favorite-sellers/users/{user}/sellers/{seller}', [FavoriteSellerController::class, 'destroyForUser']);
    Route::post('/favorite-sellers/sync', [FavoriteSellerController::class, 'sync']);
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
    Route::post('/messages/{message}/delete-by-author', [MessageController::class, 'deleteByAuthor']);
    Route::post('/conversations/{conversation}/report', [ConversationReportController::class, 'store']);
    Route::get('/conversations/{conversation}/reports', [ConversationReportController::class, 'index']);
    Route::post('/conversation-reports/{report}/resolve', [ConversationReportController::class, 'resolve']);
    // Повідомлення від адміністрації
    Route::post('/admin-messages', [AdminMessageController::class, 'store']);
    Route::get('/admin-messages/sent', [AdminMessageController::class, 'sent']);
    Route::get('/admin-messages/inbox', [AdminMessageController::class, 'inbox']);
    Route::post('/admin-messages/{message}/read', [AdminMessageController::class, 'markRead']);
    Route::get('/admin-messages/unread-count', [AdminMessageController::class, 'unreadCount']);
    Route::put('/admin-messages/{message}', [AdminMessageController::class, 'update']);
    Route::delete('/admin-messages/{message}', [AdminMessageController::class, 'destroy']);

    // Пошук та історія пошуку
    Route::get('/search/history', [SearchController::class, 'history']);
    Route::post('/search/history', [SearchController::class, 'storeHistory']);
    Route::post('/search/history/sync', [SearchController::class, 'syncHistory']);
    Route::delete('/search/history', [SearchController::class, 'clearHistory']);
    Route::delete('/search/history/{history}', [SearchController::class, 'destroyHistoryItem']);


    // ==========================================================================
    // Коментарі до товарів (review/question/answer/reaction/report)
    // ==========================================================================

    // Створення кореневого коментаря (review/question)
    Route::post('/products/{product}/comments', [ProductCommentController::class, 'store']);

    // Відповідь продавця/адміністрації на кореневий коментар
    Route::post('/comments/{comment}/answers', [ProductCommentController::class, 'storeAnswer']);

    // Реакції like/dislike
    Route::post('/comments/{comment}/reaction', [ProductCommentReactionController::class, 'upsert']);

    // Скарга на коментар
    Route::post('/comments/{comment}/report', [ProductCommentReportController::class, 'store']);

    // ==========================================================================
    // Адмін-модерація коментарів (admin/manager)
    // ==========================================================================
    Route::post('/admin/comments/{comment}/moderate', [AdminProductCommentController::class, 'moderate'])
        ->middleware('can:moderate,comment');

    Route::put('/admin/comments/{comment}', [AdminProductCommentController::class, 'update'])
        ->middleware('can:updateByAdmin,comment');

    Route::delete('/admin/comments/{comment}', [AdminProductCommentController::class, 'destroy'])
        ->middleware('can:deleteByAdmin,comment');

    // Тікети скарг на коментарі
    Route::get('/admin/comment-reports', [AdminProductCommentReportController::class, 'index'])
        ->middleware('can:viewAny,App\Models\ProductCommentReport');

    Route::post('/admin/comment-reports/{report}/resolve', [AdminProductCommentReportController::class, 'resolve'])
        ->middleware('can:resolve,report');

});

//==========================================================================
// МАРШРУТИ АУТЕНТИФІКАЦІЇ (login, register, logout...)
//==========================================================================

require __DIR__.'/auth.php';