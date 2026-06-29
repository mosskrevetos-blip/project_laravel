<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductComments\ListProductCommentsRequest;
use App\Http\Requests\ProductComments\StoreProductCommentAnswerRequest;
use App\Http\Requests\ProductComments\StoreProductCommentRequest;
use App\Http\Resources\ProductCommentResource;
use App\Models\Product;
use App\Models\ProductComment;
use App\Services\ProductCommentService;
use Illuminate\Http\JsonResponse;

class ProductCommentController extends Controller
{
    public function __construct(
        private readonly ProductCommentService $service
    ) {}

    public function index(ListProductCommentsRequest $request, Product $product): JsonResponse
    {
        $result = $this->service->listForProduct(
            product: $product,
            filters: $request->validated(),
            viewerId: auth()->id()
        );

        return response()->json([
            'data' => ProductCommentResource::collection(collect($result['data']))->resolve(),
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
        ]);
    }

    public function store(StoreProductCommentRequest $request, Product $product): JsonResponse
    {
        $comment = $this->service->createRootComment(
            product: $product,
            author: $request->user(),
            payload: $request->validated(),
            images: $request->file('images', [])
        );

        return response()->json([
            'message' => 'Коментар успішно створено та передано на модерацію.',
            'data' => (new ProductCommentResource($comment))->resolve(),
        ], 201);
    }

    public function storeAnswer(StoreProductCommentAnswerRequest $request, ProductComment $comment): JsonResponse
    {
        $answer = $this->service->createAnswer(
            rootComment: $comment,
            actor: $request->user(),
            payload: $request->validated(),
            images: $request->file('images', [])
        );

        return response()->json([
            'message' => 'Відповідь успішно опубліковано.',
            'data' => (new ProductCommentResource($answer))->resolve(),
        ], 201);
    }
}