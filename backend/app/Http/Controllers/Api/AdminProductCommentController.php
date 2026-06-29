<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductComments\AdminUpdateProductCommentRequest;
use App\Http\Requests\ProductComments\ModerateProductCommentRequest;
use App\Models\ProductComment;
use App\Services\ProductCommentService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ProductCommentResource;
use Illuminate\Http\Request;

class AdminProductCommentController extends Controller
{
    public function __construct(
        private readonly ProductCommentService $service
    ) {}

    /**
     * POST /api/admin/comments/{comment}/moderate
     * Модерація root-коментарів: approved/rejected.
     */
    public function moderate(ModerateProductCommentRequest $request, ProductComment $comment): JsonResponse
    {
        // Додатково policy (если добавили can:moderate в route, можно не дублировать)
        $this->authorize('moderate', $comment);

        $updated = $this->service->moderateComment(
            comment: $comment,
            moderator: $request->user(),
            status: $request->validated('moderation_status'),
            rejectReason: $request->validated('moderation_reject_reason')
        );

        return response()->json([
            'message' => 'Результат модерації збережено.',
            'data' => $updated,
        ]);
    }

    /**
     * PUT /api/admin/comments/{comment}
     * Редагування коментаря адміністрацією.
     */
    public function update(AdminUpdateProductCommentRequest $request, ProductComment $comment): JsonResponse
    {
        $this->authorize('updateByAdmin', $comment);

        $updated = $this->service->adminUpdateComment(
            comment: $comment,
            admin: $request->user(),
            payload: $request->validated(),
            images: $request->file('images', [])
        );

        return response()->json([
            'message' => 'Коментар оновлено.',
            'data' => $updated,
        ]);
    }

    /**
     * DELETE /api/admin/comments/{comment}
     * Видалення коментаря адміністрацією.
     */
    public function destroy(ProductComment $comment): JsonResponse
    {
        $this->authorize('deleteByAdmin', $comment);

        $this->service->adminDeleteComment(
            comment: $comment,
            admin: auth()->user()
        );

        return response()->json([
            'message' => 'Коментар видалено.',
            'ok' => true,
        ]);
    }

    /**
     * GET /api/admin/comments/moderation
     * Список коментарів для модерації.
     */
    public function moderationList(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductComment::class);

        $perPage = max(1, min((int)$request->query('per_page', 20), 100));

        $query = ProductComment::query()
            ->whereNull('parent_id')
            ->whereIn('type', ['review', 'question'])
            ->with(['author:id,name'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('moderation_status', $status);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => ProductCommentResource::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }
}