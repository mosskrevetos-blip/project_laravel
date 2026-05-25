<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageImage extends Model
{
    protected $fillable = ['message_id', 'path', 'mime', 'size'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function getUrlAttribute(): string
    {
        // assumes: php artisan storage:link
        return '/storage/' . ltrim($this->path, '/');
    }

    protected $appends = ['url'];
}