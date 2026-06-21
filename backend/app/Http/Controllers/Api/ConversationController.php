<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    // create or get conversation with seller (buyer = current user)
    public function withSeller(Request $request, User $seller)
    {
        $buyer = $request->user();

        if ($buyer->id === $seller->id) {
            return response()->json(['message' => 'Cannot start conversation with yourself'], 422);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $productId = (int) $validated['product_id'];

        $conversation = Conversation::firstOrCreate([
            'buyer_id'   => $buyer->id,
            'seller_id'  => $seller->id,
            'product_id' => $productId,
        ]);

        $conversation->load(
            'seller:id,name,last_seen_at',
            'buyer:id,name,last_seen_at',
            'product:id,title,slug,image_url,image_variants,moderation_status,is_paid,is_visible,deleted_by_user,deleted_by_admin'
        );

        return response()->json($conversation);
    }

    // list conversations
    // scope=my  -> only my conversations
    // scope=all -> all conversations (admin/manager only)
    public function index(Request $request)
    {
        $user = $request->user();

        $scope = (string) $request->query('scope', 'my');
        $isAdminOrManager = $user->hasRole('admin') || $user->hasRole('manager');

        $query = Conversation::query()
            ->with([
                'seller:id,name,last_seen_at',
                'buyer:id,name,last_seen_at',
                'product:id,title,slug,image_url,image_variants,moderation_status,is_paid,is_visible,deleted_by_user,deleted_by_admin',
            ])
            ->withCount([
                'messages as unread_count' => function ($q) use ($user) {
                    $q->whereNull('read_at')
                      ->where('sender_id', '!=', $user->id);
                },
                // ВАЖНО: чтобы в списке работала подсветка диалогов с жалобами
                'reports as reports_total_count',
                'openReports as reports_open_count',
            ]);

        if ($scope === 'all' && $isAdminOrManager) {
            // no participant filter
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('buyer_id', $user->id)
                  ->orWhere('seller_id', $user->id);
            });
        }

        $conversations = $query->latest('updated_at')->get();

        return response()->json($conversations);
    }

    public function show(Request $request, Conversation $conversation)
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

        $conversation->load([
            'buyer:id,name',
            'seller:id,name,last_seen_at',
            'product:id,title,slug,image_url,image_variants',
        ]);

        // Единый источник истины: conversation_reports
        $reportsBase = \App\Models\ConversationReport::query()
            ->where('conversation_id', $conversation->id);

        $reportsTotal = (clone $reportsBase)->count();
        $reportsOpen = (clone $reportsBase)->where('status', 'open')->count();

        $lastReport = \App\Models\ConversationReport::query()
            ->where('conversation_id', $conversation->id)
            ->with(['reporter:id,name', 'resolver:id,name'])
            ->latest('id')
            ->first();

        return response()->json([
            'id' => $conversation->id,
            'buyer_id' => $conversation->buyer_id,
            'seller_id' => $conversation->seller_id,
            'product_id' => $conversation->product_id,

            'buyer' => $conversation->buyer,
            'seller' => $conversation->seller,
            'product' => $conversation->product,

            'reports_total_count' => $reportsTotal,
            'reports_open_count' => $reportsOpen,
            'has_reports' => $reportsTotal > 0,
            'has_open_reports' => $reportsOpen > 0,

            'last_report' => $lastReport ? [
                'id' => $lastReport->id,
                'status' => $lastReport->status,
                'reason' => $lastReport->reason,
                'created_at' => $lastReport->created_at,
                'reporter' => $lastReport->reporter ? [
                    'id' => $lastReport->reporter->id,
                    'name' => $lastReport->reporter->name,
                ] : null,
                'resolved_at' => $lastReport->resolved_at,
                'resolver' => $lastReport->resolver ? [
                    'id' => $lastReport->resolver->id,
                    'name' => $lastReport->resolver->name,
                ] : null,
            ] : null,
        ]);
    }
}