<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;


class Product extends Model
{
    // використання фабрики для моделі
    use HasFactory;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
        'sku',
        'quantity',
        'rating',
        'image_url',
        'image_variants',
        'video_urls',
        'category_id',
        'secondary_category_id',
        'city',
        'properties',
        'moderation_status',
        'is_paid',
        'is_visible',
        'deleted_by_user',
        'deleted_by_admin',
    ];


    protected $casts = [
        'properties' => 'array',
        'video_urls' => 'array',
        'image_url'  => 'array',
        'image_variants' => 'array',
        'is_paid' => 'boolean',
        'is_visible' => 'boolean',
        'deleted_by_user' => 'boolean',
        'deleted_by_admin' => 'boolean',
        'rating' => 'integer',
    ];


    // Автоматична генерація slug при створенні та оновленні товару
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->title);
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('title') && empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->title);
            }
        });
    }
    

    /**
     * Генерація унікального slug
     */
    protected static function generateUniqueSlug($title)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        // Перевірка унікальності slug
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }


    /**
     * Отримати URL товару
     */
    public function getUrlAttribute()
    {
        return "/product/{$this->id}-{$this->slug}";
    }


    /**
     * Scope для "живих" товарів (те, що бачать покупці на сайті)
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('moderation_status', 'approved')
              ->where('is_visible', true)
              ->where('deleted_by_user', false)
              ->where('deleted_by_admin', false);
    }


    /**
     * Фільтрація товарів по користувачу (для кабінету продавця)
     */
    public function scopeForUser(Builder $query): void
    {
        $user = Auth::user();

        if ($user && !$user->hasRole('admin')) {
            $query->where('user_id', $user->id)
                  // Продавець не бачить те, що сам відправив у "кошик"
                    ->where('deleted_by_user', false); 
        }
    }


    // Scope для пошуку - тільки ті товари, які можна показувати в результатах пошуку
    public function scopePublishedForSearch(Builder $query): void
    {
        $query
            ->where('moderation_status', 'approved')
            ->where('is_paid', true)
            ->where('is_visible', true)
            ->where('deleted_by_user', false)
            ->where('deleted_by_admin', false);
    }


    // Зв'язок з користувачем
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    // Зв'язок з категорією
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }


    /**
     * Отримати вторинну категорію, до якої належить товар.
     */
    public function secondaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'secondary_category_id');
    }


    // Зв'язок з замовленнями
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class)->withPivot('quantity', 'price');
    }


    /**
     * Користувачі, які додали цей товар в обране
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }


    public function comments()
    {
        return $this->hasMany(ProductComment::class, 'product_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductComment::class, 'product_id')
            ->whereNull('parent_id')
            ->where('type', 'review');
    }

    public function questions()
    {
        return $this->hasMany(ProductComment::class, 'product_id')
            ->whereNull('parent_id')
            ->where('type', 'question');
    }

}