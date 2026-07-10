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

        if ($this->type === 'image' && $this->path) {
            // path хранится как basename, напр. "comment-image-123.webp"
            $basename = (string)$this->path;
            $productId = (int)$this->comment?->product_id;

            $dot = strrpos($basename, '.');
            $name = $dot === false ? $basename : substr($basename, 0, $dot);
            $ext = $dot === false ? 'webp' : substr($basename, $dot + 1);

            // дефолтный размер для списка комментариев
            $preferredSize = 800;
            $relative = "products/{$productId}/{$name}_{$preferredSize}.{$ext}";

            // fallback, если preferred отсутствует
            if (!Storage::disk('public')->exists($relative)) {
                foreach ([400, 1200, 150, 2000] as $size) {
                    $candidate = "products/{$productId}/{$name}_{$size}.{$ext}";
                    if (Storage::disk('public')->exists($candidate)) {
                        $relative = $candidate;
                        break;
                    }
                }
            }

            $url = Storage::disk('public')->url($relative);
        }

        if ($this->type === 'youtube') {
            $url = $this->external_url;
        }

        return [
            'id' => (int)$this->id,
            'type' => $this->type, // image|youtube
            'path' => $this->path, // basename для image
            'external_url' => $this->external_url,
            'url' => $url,
            'sort_order' => (int)$this->sort_order,
        ];
    }
}