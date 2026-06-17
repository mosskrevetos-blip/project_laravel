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
                }
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

        $conversation->load(
            'seller:id,name,last_seen_at',
            'buyer:id,name,last_seen_at',
            'product:id,title,slug,image_url,image_variants,moderation_status,is_paid,is_visible,deleted_by_user,deleted_by_admin'
        );

        return response()->json($conversation);
    }

    // report conversation
    public function report(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if ($conversation->buyer_id !== $user->id && $conversation->seller_id !== $user->id) {
            // при желании можно разрешить admin/manager жалобу тоже:
            // if (!($user->hasRole('admin') || $user->hasRole('manager'))) { ... }
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $conversation->is_reported = true;
        $conversation->save();

        return response()->json([
            'ok' => true,
            'conversation_id' => $conversation->id,
            'is_reported' => (bool) $conversation->is_reported,
        ]);
    }
}