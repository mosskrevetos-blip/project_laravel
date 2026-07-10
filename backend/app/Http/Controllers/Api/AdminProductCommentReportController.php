<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductComments\ResolveProductCommentReportRequest;
use App\Http\Resources\ProductCommentReportResource;
use App\Models\ProductCommentReport;
use App\Services\ProductCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductCommentReportController extends Controller
{
    public function __construct(
        private readonly ProductCommentService $service
    ) {}

    /**
     * GET /api/admin/comment-reports
     *
     * Доступ перевіряється middleware в routes/api.php:
     * ->middleware('can:viewAny,App\Models\ProductCommentReport')
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 25), 100));

        $query = ProductCommentReport::query()
            ->with([
                'comment:id,product_id,author_id,parent_id,root_id,type,body,moderation_status,created_at',
                'comment.product:id,title,slug',
                'reporter:id,name',
                'resolver:id,name',
            ]);

        // Фільтр по статусу: pending|resolved|rejected
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Фільтр по конкретному коментарю
        if ($commentId = $request->query('comment_id')) {
            $query->where('comment_id', (int) $commentId);
        }

        // Для адмінки: pending спочатку, далі rejected, далі resolved; всередині групи — новіші зверху
        $query->orderByRaw("
            CASE status
                WHEN 'pending' THEN 0
                WHEN 'rejected' THEN 1
                WHEN 'resolved' THEN 2
                ELSE 9
            END
        ")->orderByDesc('created_at');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => ProductCommentReportResource::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * POST /api/admin/comment-reports/{report}/resolve
     *
     * Доступ перевіряється middleware в routes/api.php:
     * ->middleware('can:resolve,report')
     */
    public function resolve(
        ResolveProductCommentReportRequest $request,
        ProductCommentReport $report
    ): JsonResponse {
        $updated = $this->service->resolveReport(
            report: $report,
            resolver: $request->user(),
            status: $request->validated('status'),
            resolutionNote: $request->validated('resolution_note')
        );

        return response()->json([
            'message' => 'Скаргу оброблено.',
            'data' => (new ProductCommentReportResource(
                $updated->load([
                    'reporter:id,name',
                    'resolver:id,name',
                    'comment:id,product_id,author_id,parent_id,root_id,type,body,moderation_status,created_at',
                    'comment.product:id,title,slug',
                ])
            ))->resolve(),
        ]);
    }
}