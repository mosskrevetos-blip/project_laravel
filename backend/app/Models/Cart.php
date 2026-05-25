<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    use HasFactory;

    /**
     * Поля, які можна масово заповнювати
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price',
    ];

    /**
     * Приведення типів
     */
    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
    ];

    /**
     * Зв'язок з користувачем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Зв'язок з товаром
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}