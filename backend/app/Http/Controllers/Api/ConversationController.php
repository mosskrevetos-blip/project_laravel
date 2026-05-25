<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    // create or get conversation with seller (buyer = current user)
    public function withSeller(Request $request, User $seller)
    {
        $buyer = $request->user();

        if ($buyer->id === $seller->id) {
            return response()->json(['message' => 'Cannot start conversation with yourself'], 422);
        }

        // product_id обязателен
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $productId = (int) $validated['product_id'];

        // один чат на buyer + seller + product
        $conversation = Conversation::firstOrCreate([
            'buyer_id'   => $buyer->id,
            'seller_id'  => $seller->id,
            'product_id' => $productId,
        ]);

        // полезно для UI (можешь оставить только seller если не нужно)
        $conversation->load(
            'seller:id,name,last_seen_at',
            'product:id,title' // если в Product поле называется иначе — поменяй
        );

        return response()->json($conversation);
    }

    // list conversations for current user (buyer side for now)
    public function index(Request $request)
    {
        $user = $request->user();

        // buyer only for public-app
        $conversations = Conversation::query()
            ->where('buyer_id', $user->id)
            ->with(['seller:id,name,last_seen_at', 'product:id,title'])
            ->withCount([
                'messages as unread_count' => function ($q) use ($user) {
                    $q->whereNull('read_at')->where('sender_id', '!=', $user->id);
                }
            ])
            ->latest('updated_at')
            ->get();

        return response()->json($conversations);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if ($conversation->buyer_id !== $user->id && $conversation->seller_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $conversation->load('seller:id,name,last_seen_at', 'buyer:id,name,last_seen_at', 'product:id,title');

        return response()->json($conversation);
    }
}