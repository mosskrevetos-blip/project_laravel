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
            $url = Storage::disk('public')->url($this->path);
        }

        if ($this->type === 'youtube') {
            $url = $this->external_url;
        }

        return [
            'id' => (int)$this->id,
            'type' => $this->type, // image|youtube
            'path' => $this->path,
            'external_url' => $this->external_url,
            'url' => $url,
            'sort_order' => (int)$this->sort_order,
        ];
    }
}