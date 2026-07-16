<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $likes = (int)($this->likes_count ?? 0);
        $dislikes = (int)($this->dislikes_count ?? 0);
        $answersCount = isset($this->answers_count) ? (int)$this->answers_count : null;

        return [
            'id' => (int)$this->id,
            'product_id' => (int)$this->product_id,
            'author_id' => (int)$this->author_id,
            'parent_id' => $this->parent_id ? (int)$this->parent_id : null,
            'root_id' => $this->root_id ? (int)$this->root_id : null,

            'type' => $this->type,
            'rating' => $this->rating !== null ? (int)$this->rating : null,

            'body' => $this->body,
            'pros' => $this->pros,
            'cons' => $this->cons,

            'is_verified_purchase' => (bool)$this->is_verified_purchase,
            'answer_origin' => $this->answer_origin,

            'moderation_status' => $this->moderation_status,
            'moderation_reject_reason' => $this->moderation_reject_reason,

            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => (int)$this->product->id,
                    'title' => $this->product->title,
                    'slug' => $this->product->slug,
                    'user_id' => isset($this->product->user_id) ? (int)$this->product->user_id : null, // ✅ важно
                ];
            }),

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
            'my_pending_report_exists' => (bool)($this->my_pending_report_exists ?? false),

            'answers_count' => $answersCount,
            'has_answers' => $answersCount !== null ? ($answersCount > 0) : false,

            'answers' => ProductCommentResource::collection($this->whenLoaded('answers')),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),

            'deleted_by_admin_id' => $this->deleted_by_admin_id ? (int)$this->deleted_by_admin_id : null,

            'deleted_by_admin' => $this->whenLoaded('deletedByAdmin', function () {
                return $this->deletedByAdmin ? [
                    'id' => (int)$this->deletedByAdmin->id,
                    'name' => $this->deletedByAdmin->name,
                ] : null;
            }),

            'deleted_at' => optional($this->deleted_at)->toISOString(),
            ];
    }
}