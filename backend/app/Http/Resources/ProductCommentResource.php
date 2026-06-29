<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $likes = isset($this->likes_count)
            ? (int)$this->likes_count
            : (int)($this->likes_count ?? 0);

        $dislikes = isset($this->dislikes_count)
            ? (int)$this->dislikes_count
            : (int)($this->dislikes_count ?? 0);

        return [
            'id' => (int)$this->id,
            'product_id' => (int)$this->product_id,
            'author_id' => (int)$this->author_id,
            'parent_id' => $this->parent_id ? (int)$this->parent_id : null,
            'root_id' => $this->root_id ? (int)$this->root_id : null,

            'type' => $this->type, // review|question|answer
            'rating' => $this->rating !== null ? (int)$this->rating : null,

            'body' => $this->body,
            'pros' => $this->pros,
            'cons' => $this->cons,

            'is_verified_purchase' => (bool)$this->is_verified_purchase,
            'answer_origin' => $this->answer_origin, // seller|administration|null

            'moderation_status' => $this->moderation_status,
            'moderation_reject_reason' => $this->moderation_reject_reason,

            'author' => $this->whenLoaded('author', function () {
                return [
                    'id' => (int)$this->author->id,
                    'name' => $this->author->name,
                ];
            }),

            'media' => ProductCommentMediaResource::collection($this->whenLoaded('media')),

            'likes_count' => $likes,
            'dislikes_count' => $dislikes,
            'helpfulness_score' => $likes - $dislikes,
            'answers_count' => isset($this->answers_count) ? (int)$this->answers_count : null,
            'has_answers' => isset($this->answers_count) ? ((int)$this->answers_count > 0) : (bool)$this->has_answers,

            // для корневых комментариев
            'answers' => ProductCommentResource::collection($this->whenLoaded('answers')),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}