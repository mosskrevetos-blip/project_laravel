<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductComments\ReactToProductCommentRequest;
use App\Models\ProductComment;
use App\Services\ProductCommentService;
use Illuminate\Http\JsonResponse;

class ProductCommentReactionController extends Controller
{
    public function __construct(
        private readonly ProductCommentService $service
    ) {}

    public function upsert(ReactToProductCommentRequest $request, ProductComment $comment): JsonResponse
    {
        $result = $this->service->upsertReaction(
            comment: $comment,
            user: $request->user(),
            reaction: $request->validated('reaction')
        );

        return response()->json(['data' => $result]);
    }
}