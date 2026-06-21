<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationReport;
use Illuminate\Http\Request;

class ConversationReportController extends Controller
{
    // POST /conversations/{conversation}/report
    public function store(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $isAdminOrManager = $user->hasRole('admin') || $user->hasRole('manager');

        // Разрешаем:
        // 1) участникам диалога
        // 2) admin/manager (даже если не участник)
        if (
            !$isAdminOrManager &&
            $conversation->buyer_id !== $user->id &&
            $conversation->seller_id !== $user->id
        ) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // Антидубль: не создаём новую жалобу, если у этого же пользователя уже есть открытая
        $existingOpen = ConversationReport::query()
            ->where('conversation_id', $conversation->id)
            ->where('reporter_id', $user->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($existingOpen) {
            return response()->json([
                'ok' => false,
                'message' => 'У вас уже есть открытая жалоба по этому диалогу',
                'already_exists' => true,
                'report_id' => $existingOpen->id,
            ], 409);
        }

        $report = ConversationReport::create([
            'conversation_id' => $conversation->id,
            'reporter_id' => $user->id,
            'reason' => $validated['reason'] ?? null,
            'status' => 'open',
        ]);

        return response()->json([
            'ok' => true,
            'report_id' => $report->id,
        ], 201);
    }

    // GET /conversations/{conversation}/reports
    public function index(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $isAdminOrManager = $user->hasRole('admin') || $user->hasRole('manager');

        if (
            !$isAdminOrManager &&
            $conversation->buyer_id !== $user->id &&
            $conversation->seller_id !== $user->id
        ) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $items = ConversationReport::query()
            ->where('conversation_id', $conversation->id)
            ->with([
                'reporter:id,name',
                'resolver:id,name',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'conversation_id' => $conversation->id,
            'total_count' => $items->count(),
            'open_count' => $items->where('status', 'open')->count(),
            'items' => $items,
        ]);
    }

    // POST /conversation-reports/{report}/resolve
    public function resolve(Request $request, ConversationReport $report)
    {
        $user = $request->user();
        $isAdminOrManager = $user->hasRole('admin') || $user->hasRole('manager');

        if (!$isAdminOrManager) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($report->status === 'resolved') {
            return response()->json(['ok' => true, 'already_resolved' => true]);
        }

        $report->status = 'resolved';
        $report->resolved_by = $user->id;
        $report->resolved_at = now();
        $report->resolution_note = $validated['resolution_note'] ?? null;
        $report->save();

        return response()->json(['ok' => true]);
    }
}