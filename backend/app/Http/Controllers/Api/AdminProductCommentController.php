<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductComments\AdminUpdateProductCommentRequest;
use App\Http\Requests\ProductComments\ModerateProductCommentRequest;
use App\Models\ProductComment;
use App\Services\ProductCommentService;
use Illuminate\Http\JsonResponse;

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
}