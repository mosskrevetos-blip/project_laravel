<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductComments\ReportProductCommentRequest;
use App\Models\ProductComment;
use App\Services\ProductCommentService;
use Illuminate\Http\JsonResponse;

class ProductCommentReportController extends Controller
{
    public function __construct(
        private readonly ProductCommentService $service
    ) {}

    /**
     * POST /api/comments/{comment}/report
     * Створення тікета скарги на коментар.
     */
    public function store(ReportProductCommentRequest $request, ProductComment $comment): JsonResponse
    {
        $report = $this->service->createReport(
            comment: $comment,
            reporter: $request->user(),
            reason: $request->validated('reason')
        );

        return response()->json([
            'message' => 'Скаргу успішно відправлено.',
            'data' => $report,
        ], 201);
    }
}