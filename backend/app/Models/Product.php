<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Product extends Model
{
    // используется для фабрики
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'price',
        'sku',
        'quantity',
        'image_url',
        'image_variants',
        'video_urls',
        'category_id',
        'secondary_category_id',
        'city',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
        'video_urls' => 'array',
        'image_url'  => 'array',
        'image_variants' => 'array',
    ];
    /**
     * Новый Scope для фильтрации товаров по пользователю.
     * Название метода должно начинаться со слова "scope".
     */
    public function scopeForUser(Builder $query): void
    {
        // Получаем текущего пользователя
        $user = Auth::user();

        // Если пользователь авторизован и он НЕ админ,
        // показываем только его товары.
        // Это правило будет работать и для 'seller', и для 'user', и для любой другой роли.
        if ($user && !$user->hasRole('admin')) {
            $query->where('user_id', $user->id);
        }

        // Если пользователь - админ, условие не сработает, и он увидит все товары.
        // Если пользователь - гость ($user = null), он также увидит все товары.
    }

    // Зв'язок з користувачем
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Связь с категорией
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the secondary category that the product belongs to.
     */
    public function secondaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'secondary_category_id');
    }

    // Звёязок з замовленнями
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class)->withPivot('quantity', 'price');
    }

}