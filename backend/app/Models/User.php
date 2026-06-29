<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'seller_rating',           // ✅ Рейтинг продавця
        'buyer_rating',            // ✅ Рейтинг покупця
        'wholesale_seller_rating', // ✅ Рейтинг гуртового продавця
        'manufacturer_rating',     // ✅ Рейтинг виробника
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'seller_rating' => 'integer',         
            'buyer_rating' => 'integer',            
            'wholesale_seller_rating' => 'integer', 
            'manufacturer_rating' => 'integer',
            'last_seen_at' => 'datetime',     
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    
    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Зв'язок з кошиком
     * дозволяє отримати всі товари в кошику користувача через $user->carts
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Обрані товари (через зв'язок many-to-many)
     */
    public function favoriteProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'favorites')->withTimestamps();
}

    /**
     * Обрані продавці (через зв'язок many-to-many)
     */
    public function favoriteSellerUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_sellers', 'user_id', 'seller_id')->withTimestamps();
    }

    // 
    protected function serializeDate(\DateTimeInterface $date): string
    {
        // ISO 8601, например: 2026-04-11T10:48:02+00:00
        return $date->format(\DateTime::ATOM);
    }

    /**
    * Користувачі, які додали цього продавця в обране.
    */
    public function favoritedByUsersAsSeller(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_sellers', 'seller_id', 'user_id')->withTimestamps();
    }

    // Зв'язок з історією пошуку
    public function searchHistories(): HasMany
    {
        return $this->hasMany(SearchHistory::class);
    }

    public function productComments()
    {
        return $this->hasMany(ProductComment::class, 'author_id');
    }

    public function productCommentReactions()
    {
        return $this->hasMany(ProductCommentReaction::class, 'user_id');
    }

    public function productCommentReports()
    {
        return $this->hasMany(ProductCommentReport::class, 'reporter_id');
    }
}
