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
use Illuminate\Http\Request;

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

        // ✅ Глобальные агрегаты по approved review для этого товара
        $reviewsAgg = ProductComment::query()
            ->where('product_id', $product->id)
            ->whereNull('parent_id')
            ->where('type', 'review')
            ->where('moderation_status', 'approved');

        $reviewsAvgRating = round((float) ($reviewsAgg->avg('rating') ?? 0), 1);
        $reviewsCount = (int) $reviewsAgg->count();

        return response()->json([
            'data' => ProductCommentResource::collection(collect($result['data']))->resolve(),
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],

            // ✅ для фронта
            'reviews_avg_rating' => $reviewsAvgRating,
            'reviews_count' => $reviewsCount,
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
            'data' => (new ProductCommentResource(
                $answer->load([
                    'product:id,title,slug,user_id',
                    'author:id,name',
                    'media:id,comment_id,type,path,external_url,sort_order',
                ])
            ))->resolve(),
        ], 201);
    }

    public function myComments(Request $request): JsonResponse
    {
        $result = $this->service->listForAuthor(
            authorId: (int)$request->user()->id,
            filters: $request->only(['type', 'status', 'page', 'per_page'])
        );

        return response()->json([
            'data' => ProductCommentResource::collection(collect($result['data']))->resolve(),
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
        ]);
    }
}