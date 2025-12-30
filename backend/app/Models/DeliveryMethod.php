<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeliveryMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'price',
        'active',
        'meta',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];
}