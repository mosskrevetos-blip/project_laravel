<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ConversationReport;

class Conversation extends Model
{
    protected $fillable = ['buyer_id', 'seller_id', 'product_id'];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ConversationReport::class);
    }

    public function openReports(): HasMany
    {
        return $this->hasMany(ConversationReport::class)->where('status', 'open');
    }
}