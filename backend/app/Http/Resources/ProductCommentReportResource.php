<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCommentReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int)$this->id,
            'comment_id' => (int)$this->comment_id,
            'reporter_id' => (int)$this->reporter_id,
            'status' => $this->status, // pending|resolved|rejected
            'reason' => $this->reason,
            'resolution_note' => $this->resolution_note,
            'resolved_by' => $this->resolved_by ? (int)$this->resolved_by : null,
            'resolved_at' => optional($this->resolved_at)->toISOString(),

            'comment' => $this->whenLoaded('comment', function () {
                return [
                    'id' => (int)$this->comment->id,
                    'type' => $this->comment->type,
                    'body' => $this->comment->body,
                    'author_id' => (int)$this->comment->author_id,
                    'product_id' => (int)$this->comment->product_id,
                    'moderation_status' => $this->comment->moderation_status,
                    'created_at' => optional($this->comment->created_at)->toISOString(),
                ];
            }),

            'reporter' => $this->whenLoaded('reporter', function () {
                return [
                    'id' => (int)$this->reporter->id,
                    'name' => $this->reporter->name,
                ];
            }),

            'resolver' => $this->whenLoaded('resolver', function () {
                return [
                    'id' => (int)$this->resolver->id,
                    'name' => $this->resolver->name,
                ];
            }),

            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}