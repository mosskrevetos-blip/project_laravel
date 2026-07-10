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
        'type',         // image | youtube
        'path',         // basename for local image
        'external_url', // youtube url
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

    /**
     * Unified media URL for frontend:
     * - youtube => external_url
     * - image   => generated public URL to preferred variant
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->type === 'youtube') {
            return $this->external_url;
        }

        if (!$this->path) {
            return null;
        }

        $basename = (string) $this->path;
        $productId = (int) ($this->comment?->product_id ?? 0);

        if (!$productId) {
            return null;
        }

        $dot = strrpos($basename, '.');
        $name = $dot === false ? $basename : substr($basename, 0, $dot);
        $ext = $dot === false ? 'webp' : substr($basename, $dot + 1);

        $disk = Storage::disk('public');

        // preferred preview size
        $candidate = "products/{$productId}/{$name}_800.{$ext}";

        if (!$disk->exists($candidate)) {
            foreach ([400, 1200, 150, 2000] as $size) {
                $fallback = "products/{$productId}/{$name}_{$size}.{$ext}";
                if ($disk->exists($fallback)) {
                    $candidate = $fallback;
                    break;
                }
            }
        }

        // final fallback: original basename in folder
        if (!$disk->exists($candidate)) {
            $original = "products/{$productId}/{$basename}";
            if ($disk->exists($original)) {
                $candidate = $original;
            } else {
                return null;
            }
        }

        return $disk->url($candidate);
    }
}