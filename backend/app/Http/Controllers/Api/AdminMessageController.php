<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminMessage;
use App\Models\AdminMessageAttachment;
use App\Models\AdminMessageRecipient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminMessageController extends Controller
{
    protected function isAdminOrManager($user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager');
    }

    // POST /admin-messages
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$this->isAdminOrManager($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'send_to_all' => ['required', 'boolean'],
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:8192'], // 8MB
        ]);

        $sendToAll = (bool) $validated['send_to_all'];
        $recipientIds = collect($validated['recipient_ids'] ?? [])->map(fn ($v) => (int) $v)->unique()->values();

        if (!$sendToAll && $recipientIds->isEmpty()) {
            return response()->json(['message' => 'recipient_ids is required when send_to_all=false'], 422);
        }

        $message = DB::transaction(function () use ($user, $validated, $sendToAll, $recipientIds, $request) {
            $msg = AdminMessage::create([
                'sender_id' => $user->id,
                'subject' => trim($validated['subject']),
                'body' => trim($validated['body']),
                'is_broadcast' => $sendToAll,
                'sent_at' => now(),
            ]);

            $targetUserIds = $sendToAll
                ? User::query()
                    ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'manager']))
                    ->pluck('id')
                : $recipientIds;

            $rows = [];
            $now = now();
            foreach ($targetUserIds as $uid) {
                $rows[] = [
                    'admin_message_id' => $msg->id,
                    'recipient_id' => (int) $uid,
                    'status' => 'unread',
                    'read_at' => null,
                    'status_changed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($rows)) {
                AdminMessageRecipient::insert($rows);
            }

            if ($request->hasFile('attachments')) {
                foreach ((array) $request->file('attachments') as $file) {
                    $dir = "admin-messages/{$msg->id}";
                    $filename = Str::uuid()->toString() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    $path = $file->storeAs($dir, $filename, 'public');

                    AdminMessageAttachment::create([
                        'admin_message_id' => $msg->id,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'size' => (int) $file->getSize(),
                    ]);
                }
            }

            return $msg;
        });

        return response()->json([
            'ok' => true,
            'message_id' => $message->id,
        ], 201);
    }

    // GET /admin-messages/sent (admin/manager)
    public function sent(Request $request)
    {
        $user = $request->user();
        if (!$this->isAdminOrManager($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $perPage = min((int) $request->query('per_page', 30), 100);

        $query = AdminMessage::query()
            ->with([
                'sender:id,name',
                'attachments:id,admin_message_id,original_name,mime,size,path',
                'recipients' => function ($q) {
                    $q->with('recipient:id,name')
                      ->select('id', 'admin_message_id', 'recipient_id', 'status', 'read_at', 'status_changed_at');
                },
            ])
            ->orderByDesc('id');

        $items = $query->paginate($perPage);

        return response()->json($items);
    }

    // GET /admin-messages/inbox (user)
    public function inbox(Request $request)
    {
        $user = $request->user();
        $perPage = min((int) $request->query('per_page', 30), 100);

        $items = AdminMessageRecipient::query()
            ->where('recipient_id', $user->id)
            ->whereIn('status', ['unread', 'read'])
            ->with([
                'message' => function ($q) {
                    $q->with([
                        'sender:id,name',
                        'attachments:id,admin_message_id,original_name,mime,size,path'
                    ])->select('id', 'sender_id', 'subject', 'body', 'is_broadcast', 'sent_at', 'created_at', 'updated_at');
                },
            ])
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($items);
    }

    // POST /admin-messages/{message}/read
    public function markRead(Request $request, AdminMessage $message)
    {
        $user = $request->user();

        $recipient = AdminMessageRecipient::query()
            ->where('admin_message_id', $message->id)
            ->where('recipient_id', $user->id)
            ->first();

        if (!$recipient) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($recipient->status === 'unread') {
            $recipient->status = 'read';
            $recipient->read_at = now();
            $recipient->status_changed_at = now();
            $recipient->save();
        }

        return response()->json(['ok' => true]);
    }

    // PUT /admin-messages/{message}
    public function update(Request $request, AdminMessage $message)
    {
        $user = $request->user();
        if (!$this->isAdminOrManager($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Разрешим редактировать только отправителю или админу
        if ((int) $message->sender_id !== (int) $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:8192'],
            'remove_attachment_ids' => ['nullable', 'array'],
            'remove_attachment_ids.*' => ['integer'],
        ]);

        DB::transaction(function () use ($message, $validated, $request) {
            $message->subject = trim($validated['subject']);
            $message->body = trim($validated['body']);
            $message->updated_content_at = now();
            $message->save();

            $removeIds = collect($validated['remove_attachment_ids'] ?? [])->map(fn ($v) => (int) $v)->all();
            if (!empty($removeIds)) {
                $toDelete = AdminMessageAttachment::query()
                    ->where('admin_message_id', $message->id)
                    ->whereIn('id', $removeIds)
                    ->get();

                foreach ($toDelete as $att) {
                    Storage::disk('public')->delete($att->path);
                    $att->delete();
                }
            }

            if ($request->hasFile('attachments')) {
                foreach ((array) $request->file('attachments') as $file) {
                    $dir = "admin-messages/{$message->id}";
                    $filename = Str::uuid()->toString() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                    $path = $file->storeAs($dir, $filename, 'public');

                    AdminMessageAttachment::create([
                        'admin_message_id' => $message->id,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'size' => (int) $file->getSize(),
                    ]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    // DELETE /admin-messages/{message}  (soft delete to recipients status=deleted)
    public function destroy(Request $request, AdminMessage $message)
    {
        $user = $request->user();
        if (!$this->isAdminOrManager($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ((int) $message->sender_id !== (int) $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        DB::transaction(function () use ($message) {
            $message->deleted_at_by_sender = now();
            $message->save();

            AdminMessageRecipient::query()
                ->where('admin_message_id', $message->id)
                ->whereIn('status', ['unread', 'read'])
                ->update([
                    'status' => 'deleted',
                    'status_changed_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return response()->json(['ok' => true]);
    }

    // GET /admin-messages/unread-count  (для суммирования с обычным чатом)
    public function unreadCount(Request $request)
    {
        $user = $request->user();

        $count = AdminMessageRecipient::query()
            ->where('recipient_id', $user->id)
            ->where('status', 'unread')
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}