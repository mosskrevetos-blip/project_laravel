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

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductCommentReport::class);

        $perPage = max(1, min((int)$request->query('per_page', 25), 100));

        $query = ProductCommentReport::query()
            ->with([
                'comment:id,product_id,author_id,type,body,moderation_status,created_at',
                'reporter:id,name',
                'resolver:id,name',
            ])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($commentId = $request->query('comment_id')) {
            $query->where('comment_id', (int)$commentId);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => ProductCommentReportResource::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function resolve(
        ResolveProductCommentReportRequest $request,
        ProductCommentReport $report
    ): JsonResponse {
        $this->authorize('resolve', $report);

        $updated = $this->service->resolveReport(
            report: $report,
            resolver: $request->user(),
            status: $request->validated('status'),
            resolutionNote: $request->validated('resolution_note')
        );

        return response()->json([
            'message' => 'Скаргу оброблено.',
            'data' => (new ProductCommentReportResource($updated->load(['reporter:id,name', 'resolver:id,name', 'comment:id,product_id,author_id,type,body,moderation_status,created_at'])))->resolve(),
        ]);
    }
}