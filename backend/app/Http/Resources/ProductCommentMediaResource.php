<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductCommentMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = null;
        $variants = null;

        if ($this->type === 'image') {
            $variants = is_array($this->variants) ? $this->variants : null;

            if (is_array($variants)) {
                $url = $variants['800']['webp']
                    ?? $variants['400']['webp']
                    ?? $variants['1200']['webp']
                    ?? $variants['150']['webp']
                    ?? $variants['2000']['webp']
                    ?? null;
            }

            if (!$url && $this->path && $this->comment_id) {
                $relative = "comments/{$this->comment_id}/{$this->path}";
                if (Storage::disk('public')->exists($relative)) {
                    $url = Storage::disk('public')->url($relative);
                }
            }
        }

        if ($this->type === 'youtube') {
            $url = $this->external_url;
        }

        return [
            'id' => (int)$this->id,
            'type' => $this->type,
            'path' => $this->path,
            'external_url' => $this->external_url,
            'url' => $url,
            'variants' => $variants,
            'sort_order' => (int)$this->sort_order,
        ];
    }
}