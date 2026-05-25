<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class MessageController extends Controller
{
    public function index(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if ($conversation->buyer_id !== $user->id && $conversation->seller_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $perPage = (int) $request->query('per_page', 30);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with(['images'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($messages);
    }

    private function storeChatImageAsWebp(UploadedFile $file, int $senderId, int $productId): array
    {
        $dir = "chat/{$senderId}/products/{$productId}";

        // имя файла (уникальное)
        // Пример: img_20260411_134502_1712849102_ab12cd34.webp
        $tsHuman = now()->format('Ymd_His');          // 20260411_134502
        $tsUnix  = now()->timestamp;                 // 1712849102
        $rand    = bin2hex(random_bytes(4));         // 8 hex chars
        $fileName = "img_{$tsHuman}_{$tsUnix}_{$rand}.webp";
        $path = "{$dir}/{$fileName}";

        $image = match ($file->getMimeType()) {
            'image/jpeg' => @imagecreatefromjpeg($file->getPathname()),
            'image/png'  => @imagecreatefrompng($file->getPathname()),
            'image/webp' => @imagecreatefromwebp($file->getPathname()),
            default => null,
        };

        if (!$image) {
            throw new \RuntimeException('Unsupported image type or corrupted file.');
        }

        // для PNG/палитровых изображений
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }
        @imagealphablending($image, true);
        @imagesavealpha($image, true);

        // качество webp (можешь поменять на 90 при желании)
        $quality = 80;

        // ВАЖНО: не делаем resize => исходное разрешение сохраняется
        ob_start();
        $ok = @imagewebp($image, null, $quality);
        $webp = ob_get_clean();
        imagedestroy($image);

        if (!$ok || $webp === false || $webp === '') {
            throw new \RuntimeException('Failed to encode image to webp.');
        }

        Storage::disk('public')->makeDirectory($dir);
        Storage::disk('public')->put($path, $webp);

        return [
            'path' => $path,
            'mime' => 'image/webp',
            'size' => strlen($webp),
        ];
    }


    public function store(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if ($conversation->buyer_id !== $user->id && $conversation->seller_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'body' => 'nullable|string',
            'product_id' => 'required|exists:products,id',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096', // 4MB
        ]);

        if ((int)$validated['product_id'] !== (int)$conversation->product_id) {
            return response()->json(['message' => 'product_id does not match conversation'], 422);
        }

        if (
            (!isset($validated['body']) || trim((string)$validated['body']) === '')
            && !$request->hasFile('image')
        ) {
            return response()->json(['message' => 'Message must have text or image'], 422);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $validated['body'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');

            $stored = $this->storeChatImageAsWebp($file, $user->id, (int) $validated['product_id']);

            MessageImage::create([
                'message_id' => $message->id,
                'path' => $stored['path'],
                'mime' => $stored['mime'],
                'size' => (int) $stored['size'],
            ]);
        }

        $message->load('images');

        return response()->json($message, 201);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if ($conversation->buyer_id !== $user->id && $conversation->seller_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}