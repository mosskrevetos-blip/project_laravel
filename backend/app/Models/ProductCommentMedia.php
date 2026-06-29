<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCommentMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'comment_id',
        'type',
        'path',
        'external_url',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'url',
    ];

    public function comment()
    {
        return $this->belongsTo(ProductComment::class, 'comment_id');
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->type === 'youtube') {
            return $this->external_url;
        }

        if (!$this->path) {
            return null;
        }

        // Подстройте disk при необходимости: public/s3
        return \Storage::disk('public')->url($this->path);
    }
}