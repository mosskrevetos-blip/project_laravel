<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductComments\AdminUpdateProductCommentRequest;
use App\Http\Requests\ProductComments\ModerateProductCommentRequest;
use App\Http\Resources\ProductCommentResource;
use App\Models\ProductComment;
use App\Services\ProductCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductCommentController extends Controller
{
    public function __construct(
        private readonly ProductCommentService $service
    ) {}

    /**
     * GET /api/admin/comments/moderation
     * Список кореневих коментарів (review/question) для адмін-модерації.
     */
    public function moderationList(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $query = ProductComment::query()
            ->whereNull('parent_id')
            ->whereIn('type', ['review', 'question'])
            ->withEngagementStats()
            ->withCount('answers')
            ->with([
                'product:id,title,slug,user_id',
                'author:id,name',
                'media:id,comment_id,type,path,external_url,sort_order',
                'media.comment:id,product_id',
                'answers.product:id,title,slug,user_id',
                'answers.author:id,name',
                'answers.media:id,comment_id,type,path,external_url,sort_order',
                'answers.media.comment:id,product_id',
            ]);

        if ($status = $request->query('status')) {
            $query->where('moderation_status', $status);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // Стабильная сортировка для админки:
        // pending -> rejected -> approved, внутри групп новые сверху
        $query->orderByRaw("
            CASE moderation_status
                WHEN 'pending' THEN 0
                WHEN 'rejected' THEN 1
                WHEN 'approved' THEN 2
                ELSE 9
            END
        ")->orderByDesc('created_at');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => ProductCommentResource::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * POST /api/admin/comments/{comment}/moderate
     * Модерація root-коментарів: approved/rejected.
     */
    public function moderate(ModerateProductCommentRequest $request, ProductComment $comment): JsonResponse
    {
        $updated = $this->service->moderateComment(
            comment: $comment,
            moderator: $request->user(),
            status: $request->validated('moderation_status'),
            rejectReason: $request->validated('moderation_reject_reason')
        );

        return response()->json([
            'message' => 'Результат модерації збережено.',
            'data' => (new ProductCommentResource(
                $updated->load([
                    'product:id,title,slug,user_id',
                    'author:id,name',
                    'media:id,comment_id,type,path,external_url,sort_order',
                    'media.comment:id,product_id',
                    'answers.product:id,title,slug,user_id',
                    'answers.author:id,name',
                    'answers.media:id,comment_id,type,path,external_url,sort_order',
                    'answers.media.comment:id,product_id',
                ])
            ))->resolve(),
        ]);
    }

    /**
     * PUT /api/admin/comments/{comment}
     * (також підтримується POST + _method=PUT для multipart/form-data)
     */
    public function update(AdminUpdateProductCommentRequest $request, ProductComment $comment): JsonResponse
    {
        $updated = $this->service->adminUpdateComment(
            comment: $comment,
            admin: $request->user(),
            payload: $request->validated(),
            images: $request->file('images', [])
        );

        return response()->json([
            'message' => 'Коментар оновлено.',
            'data' => (new ProductCommentResource(
                $updated->load([
                    'product:id,title,slug,user_id',
                    'author:id,name',
                    'media:id,comment_id,type,path,external_url,sort_order',
                    'media.comment:id,product_id',
                    'answers.product:id,title,slug,user_id',
                    'answers.author:id,name',
                    'answers.media:id,comment_id,type,path,external_url,sort_order',
                    'answers.media.comment:id,product_id',
                ])
            ))->resolve(),
        ]);
    }

    /**
     * DELETE /api/admin/comments/{comment}
     * Видалення коментаря/відповіді адміністрацією.
     */
    public function destroy(ProductComment $comment): JsonResponse
    {
        $this->service->adminDeleteComment(
            comment: $comment,
            admin: auth()->user()
        );

        return response()->json([
            'message' => 'Коментар видалено.',
            'ok' => true,
        ]);
    }
}