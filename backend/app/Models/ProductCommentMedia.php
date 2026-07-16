<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductCommentMedia extends Model
{
    use HasFactory;

    protected $table = 'product_comment_media';

    protected $fillable = [
        'comment_id',
        'type',
        'path',
        'external_url',
        'variants',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'variants' => 'array',
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

        if ($this->type !== 'image') {
            return null;
        }

        $variants = (array) $this->variants;
        if (!empty($variants['800']['webp'])) return $variants['800']['webp'];
        if (!empty($variants['400']['webp'])) return $variants['400']['webp'];
        if (!empty($variants['1200']['webp'])) return $variants['1200']['webp'];
        if (!empty($variants['150']['webp'])) return $variants['150']['webp'];
        if (!empty($variants['2000']['webp'])) return $variants['2000']['webp'];

        if (!$this->path || !$this->comment_id) {
            return null;
        }

        $relative = "comments/{$this->comment_id}/{$this->path}";
        if (!Storage::disk('public')->exists($relative)) {
            return null;
        }

        return Storage::disk('public')->url($relative);
    }
}