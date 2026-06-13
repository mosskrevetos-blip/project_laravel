<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class MessageController extends Controller
{
    /**
     * List messages in conversation
     */
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

        $perPage = min((int) $request->get('per_page', 100), 100);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('images')
            ->orderByDesc('id')
            ->paginate($perPage);

        // hide deleted content, but keep message row
        $messages->getCollection()->transform(function ($m) {
            if ((bool) $m->deleted_by_user) {
                $m->body = null;
                $m->setRelation('images', collect());
            }
            return $m;
        });

        return response()->json($messages);
    }

    /**
     * Send message
     */
    public function store(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if ($conversation->buyer_id !== $user->id && $conversation->seller_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'body'       => ['nullable', 'string', 'max:5000'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'image'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $hasBody = isset($validated['body']) && trim((string) $validated['body']) !== '';
        $hasImage = $request->hasFile('image');

        if (!$hasBody && !$hasImage) {
            return response()->json(['message' => 'Message body or image is required'], 422);
        }

        if ((int) $validated['product_id'] !== (int) $conversation->product_id) {
            return response()->json(['message' => 'Invalid product context'], 422);
        }

        $message = DB::transaction(function () use ($conversation, $user, $validated, $request) {
            $message = Message::create([
                'conversation_id'  => $conversation->id,
                'sender_id'        => $user->id,
                'product_id'       => $validated['product_id'],
                'body'             => isset($validated['body']) ? trim((string) $validated['body']) : null,
                'deleted_by_user'  => false,
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $url = $this->storeChatImageAsWebp($conversation->id, $message->id, $file);

                MessageImage::create([
                    'message_id' => $message->id,
                    'url'        => $url,
                ]);
            }

            $conversation->updated_at = now();
            $conversation->save();

            return $message;
        });

        $message->load('images');

        return response()->json($message, 201);
    }

    /**
     * Mark messages as read
     * IMPORTANT:
     * - Admin/manager in scope=all can OPEN chats, but should NOT change read status.
     *   So for admin/manager we return ok without updates.
     */
    public function markRead(Request $request, Conversation $conversation)
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

        // Do not change read statuses for admin/manager moderation view
        if ($isAdminOrManager) {
            return response()->json([
                'ok' => true,
                'updated' => 0,
                'skipped' => 'admin_or_manager_view',
            ]);
        }

        $validated = $request->validate([
            'message_ids'   => ['nullable', 'array'],
            'message_ids.*' => ['integer'],
        ]);

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id);

        if (!empty($validated['message_ids'])) {
            $query->whereIn('id', $validated['message_ids']);
        }

        $updated = $query->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'updated' => $updated,
        ]);
    }

    /**
     * Author deletes own message (soft flag)
     */
    public function deleteByAuthor(Request $request, Message $message)
    {
        $user = $request->user();

        if ((int) $message->sender_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $conversation = Conversation::find($message->conversation_id);
        if (!$conversation || ($conversation->buyer_id !== $user->id && $conversation->seller_id !== $user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $message->deleted_by_user = true;
        $message->save();

        return response()->json([
            'ok' => true,
            'message_id' => $message->id,
        ]);
    }

    /**
     * Save chat image as webp
     */
    protected function storeChatImageAsWebp(int $conversationId, int $messageId, $file): string
    {
        $manager = new ImageManager(new Driver());

        $image = $manager->read($file->getPathname());

        $maxW = 1600;
        $maxH = 1600;

        if ($image->width() > $maxW || $image->height() > $maxH) {
            $image->scaleDown($maxW, $maxH);
        }

        $fileName = Str::uuid()->toString() . '.webp';
        $dir = "chat/{$conversationId}/{$messageId}";
        $path = "{$dir}/{$fileName}";

        Storage::disk('public')->put($path, (string) $image->toWebp(82));

        return Storage::url($path); // e.g. /storage/chat/...
    }
}