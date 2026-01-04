<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        // старые/общие поля
        'total_price',
        'status',
        'user_id',
        // buyer
        'buyer_first_name',
        'buyer_last_name',
        'buyer_middle_name',
        'buyer_phone',
        'buyer_email',
        // recipient
        'recipient_first_name',
        'recipient_last_name',
        'recipient_middle_name',
        'recipient_phone',
        // seller/delivery/payment
        'seller_id',
        'delivery_method_id',
        'payment_method_id',
        'city',
        'address',
        // новые: payment/tracking
        'payment_status',
        'paid_at',
        'tracking_number',
        'carrier',
        'estimated_delivery_date',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'delivery_price' => 'decimal:2',
        'paid_at' => 'datetime',
        'estimated_delivery_date' => 'datetime',
    ];

    // отношения (как было)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity', 'price')->withTimestamps();
    }

    public function deliveryMethod(): BelongsTo
    {
        return $this->belongsTo(DeliveryMethod::class, 'delivery_method_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}