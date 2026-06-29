<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\ProductCommentMedia;
use App\Models\ProductCommentReaction;
use App\Models\ProductCommentReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductCommentService
{
    /**
     * Публічний/авторський список коментарів по товару.
     */
    public function listForProduct(Product $product, array $filters, ?int $viewerId = null): array
    {
        $type = $filters['type'] ?? 'review';
        $sort = $filters['sort'] ?? 'date_desc';
        $perPage = (int)($filters['per_page'] ?? 25);
        $perPage = max(1, min($perPage, 100));

        $query = ProductComment::query()
            ->forProduct($product->id)
            ->root()
            ->type($type)
            ->visibleForUser($viewerId)
            ->withEngagementStats()
            ->withPublicRelations();

        // Фільтри
        if ($type === 'review' && !empty($filters['rating'])) {
            $query->where('rating', (int)$filters['rating']);
        }

        if (isset($filters['verified']) && (int)$filters['verified'] === 1) {
            $query->where('is_verified_purchase', true);
        }

        if (isset($filters['with_photos']) && (int)$filters['with_photos'] === 1) {
            $query->whereHas('media', fn ($q) => $q->where('type', 'image'));
        }

        // Сортування
        if ($type === 'review') {
            $query->sortReviews($sort);
        } else {
            $query->sortQuestions($sort);
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * Створити root review/question (з премодерацією).
     */
    public function createRootComment(Product $product, User $author, array $payload, array $images = []): ProductComment
    {
        return DB::transaction(function () use ($product, $author, $payload, $images) {
            $type = $payload['type'];

            $comment = new ProductComment();
            $comment->product_id = $product->id;
            $comment->author_id = $author->id;
            $comment->parent_id = null;
            $comment->root_id = null;
            $comment->type = $type;
            $comment->rating = $type === 'review' ? (int)($payload['rating'] ?? 0) : null;
            $comment->body = $payload['body'] ?? null;
            $comment->pros = $type === 'review' ? ($payload['pros'] ?? null) : null;
            $comment->cons = $type === 'review' ? ($payload['cons'] ?? null) : null;
            $comment->is_verified_purchase = $this->resolveVerifiedPurchase($author->id, $product->id, $product->user_id ?? null);
            $comment->answer_origin = null;
            $comment->moderation_status = 'pending';
            $comment->save();

            // root_id = self id
            $comment->root_id = $comment->id;
            $comment->save();

            $this->syncMedia($comment, $images, $payload['youtube_url'] ?? null);

            return $comment->fresh([
                'author:id,name',
                'media:id,comment_id,type,path,external_url,sort_order',
            ]);
        });
    }

    /**
     * Відповідь продавця/адміністрації на root comment.
     * Відповідь публікується одразу (approved).
     */
    public function createAnswer(ProductComment $rootComment, User $actor, array $payload, array $images = []): ProductComment
    {
        if ($rootComment->parent_id !== null) {
            throw ValidationException::withMessages([
                'comment' => 'Відповідати можна тільки на кореневий коментар.',
            ]);
        }

        if (!in_array($rootComment->type, ['review', 'question'], true)) {
            throw ValidationException::withMessages([
                'comment' => 'Відповідь можлива тільки для відгуку або питання.',
            ]);
        }

        return DB::transaction(function () use ($rootComment, $actor, $payload, $images) {
            $product = $rootComment->product()->firstOrFail();
            $isAdminOrManager = method_exists($actor, 'hasRole')
                ? ($actor->hasRole('admin') || $actor->hasRole('manager'))
                : false;

            $isSellerOwner = ((int)($product->user_id ?? 0) === (int)$actor->id);

            if (!$isAdminOrManager && !$isSellerOwner) {
                throw ValidationException::withMessages([
                    'comment' => 'Недостатньо прав для відповіді.',
                ]);
            }

            $answer = new ProductComment();
            $answer->product_id = $rootComment->product_id;
            $answer->author_id = $actor->id;
            $answer->parent_id = $rootComment->id;
            $answer->root_id = $rootComment->id;
            $answer->type = 'answer';
            $answer->rating = null;
            $answer->body = $payload['body'] ?? null;
            $answer->pros = null;
            $answer->cons = null;
            $answer->is_verified_purchase = false;
            $answer->answer_origin = $isAdminOrManager ? 'administration' : 'seller';
            $answer->moderation_status = 'approved';
            $answer->save();

            $this->syncMedia($answer, $images, $payload['youtube_url'] ?? null);

            return $answer->fresh([
                'author:id,name',
                'media:id,comment_id,type,path,external_url,sort_order',
            ]);
        });
    }

    /**
     * 1 реакція на користувача/коментар, з перемиканням like/dislike.
     */
    public function upsertReaction(ProductComment $comment, User $user, string $reaction): array
    {
        if (!in_array($reaction, ['like', 'dislike'], true)) {
            throw ValidationException::withMessages([
                'reaction' => 'Дозволені значення: like або dislike.',
            ]);
        }

        DB::transaction(function () use ($comment, $user, $reaction) {
            $row = ProductCommentReaction::query()
                ->where('comment_id', $comment->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                ProductCommentReaction::create([
                    'comment_id' => $comment->id,
                    'user_id' => $user->id,
                    'reaction' => $reaction,
                ]);
                return;
            }

            // Переключення
            if ($row->reaction !== $reaction) {
                $row->reaction = $reaction;
                $row->save();
            }
        });

        $likes = ProductCommentReaction::query()
            ->where('comment_id', $comment->id)
            ->where('reaction', 'like')
            ->count();

        $dislikes = ProductCommentReaction::query()
            ->where('comment_id', $comment->id)
            ->where('reaction', 'dislike')
            ->count();

        return [
            'comment_id' => $comment->id,
            'likes_count' => (int)$likes,
            'dislikes_count' => (int)$dislikes,
            'helpfulness_score' => (int)$likes - (int)$dislikes,
            'my_reaction' => $reaction,
        ];
    }

    /**
     * Створити тікет скарги (1 активна pending на comment/user).
     */
    public function createReport(ProductComment $comment, User $reporter, string $reason): ProductCommentReport
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Причина скарги обов’язкова.',
            ]);
        }

        return DB::transaction(function () use ($comment, $reporter, $reason) {
            $existsPending = ProductCommentReport::query()
                ->where('comment_id', $comment->id)
                ->where('reporter_id', $reporter->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();

            if ($existsPending) {
                throw ValidationException::withMessages([
                    'reason' => 'У вас вже є активна скарга на цей коментар.',
                ]);
            }

            return ProductCommentReport::create([
                'comment_id' => $comment->id,
                'reporter_id' => $reporter->id,
                'reason' => $reason,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Модерація root comment: approved/rejected.
     */
    public function moderateComment(
        ProductComment $comment,
        User $moderator,
        string $status,
        ?string $rejectReason = null
    ): ProductComment {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'moderation_status' => 'Дозволені статуси: approved або rejected.',
            ]);
        }

        if ($comment->parent_id !== null) {
            throw ValidationException::withMessages([
                'comment' => 'Модерація застосовується тільки до кореневих коментарів.',
            ]);
        }

        if (!in_array($comment->type, ['review', 'question'], true)) {
            throw ValidationException::withMessages([
                'comment' => 'Модерація доступна тільки для review/question.',
            ]);
        }

        if ($status === 'rejected' && trim((string)$rejectReason) === '') {
            throw ValidationException::withMessages([
                'moderation_reject_reason' => 'Для відхилення потрібно вказати причину.',
            ]);
        }

        $comment->moderation_status = $status;
        $comment->moderation_reject_reason = $status === 'rejected' ? trim((string)$rejectReason) : null;
        $comment->moderated_by = $moderator->id;
        $comment->moderated_at = now();
        $comment->save();

        return $comment->fresh(['author:id,name']);
    }

    /**
     * Редагування коментаря адміністрацією.
     */
    public function adminUpdateComment(
        ProductComment $comment,
        User $admin,
        array $payload,
        array $images = []
    ): ProductComment {
        return DB::transaction(function () use ($comment, $admin, $payload, $images) {
            if (array_key_exists('body', $payload)) {
                $comment->body = $payload['body'];
            }

            if ($comment->type === 'review') {
                if (array_key_exists('pros', $payload)) {
                    $comment->pros = $payload['pros'];
                }
                if (array_key_exists('cons', $payload)) {
                    $comment->cons = $payload['cons'];
                }
                if (array_key_exists('rating', $payload)) {
                    $comment->rating = $payload['rating'];
                }
            }

            $comment->edited_by_admin_id = $admin->id;
            $comment->save();

            // Удаление выбранных медиа
            $removeIds = collect($payload['remove_media_ids'] ?? [])->map(fn ($id) => (int)$id)->unique()->values();
            if ($removeIds->isNotEmpty()) {
                $toDelete = ProductCommentMedia::query()
                    ->where('comment_id', $comment->id)
                    ->whereIn('id', $removeIds)
                    ->get();

                foreach ($toDelete as $media) {
                    if ($media->type === 'image' && $media->path) {
                        Storage::disk('public')->delete($media->path);
                    }
                    $media->delete();
                }
            }

            // Якщо прийшли нові медіа — додаємо (не зносимо існуючі)
            if (!empty($images) || !empty($payload['youtube_url'])) {
                $this->appendMedia($comment, $images, $payload['youtube_url'] ?? null);
            }

            return $comment->fresh([
                'author:id,name',
                'media:id,comment_id,type,path,external_url,sort_order',
            ]);
        });
    }

    /**
     * Видалення коментаря адміністрацією (soft delete).
     * Додатково видаляємо файли image з диска.
     */
    public function adminDeleteComment(ProductComment $comment, User $admin): void
    {
        DB::transaction(function () use ($comment, $admin) {
            // Помечаем, кто удалил
            $comment->deleted_by_admin_id = $admin->id;
            $comment->save();

            // Удаляем файлы текущего comment
            $media = ProductCommentMedia::query()
                ->where('comment_id', $comment->id)
                ->get();

            foreach ($media as $m) {
                if ($m->type === 'image' && $m->path) {
                    Storage::disk('public')->delete($m->path);
                }
            }

            // Можно оставить media записи, но обычно мягко чистят при soft delete коммента.
            // Если хотите сохранить аудит — закомментируйте:
            ProductCommentMedia::query()->where('comment_id', $comment->id)->delete();

            $comment->delete();
        });
    }

    /**
     * Обробка тікета скарги.
     */
    public function resolveReport(
        ProductCommentReport $report,
        User $resolver,
        string $status,
        ?string $resolutionNote = null
    ): ProductCommentReport {
        if (!in_array($status, ['resolved', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Дозволені статуси: resolved або rejected.',
            ]);
        }

        $report->status = $status;
        $report->resolution_note = $resolutionNote;
        $report->resolved_by = $resolver->id;
        $report->resolved_at = now();
        $report->save();

        return $report->fresh(['reporter:id,name', 'resolver:id,name']);
    }

    /**
     * Визначення підтвердженої покупки.
     * Підлаштуйте статуси під вашу систему Order.
     */
    private function resolveVerifiedPurchase(int $userId, int $productId, ?int $sellerId = null): bool
    {
        $query = Order::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['paid', 'completed', 'delivered'])
            ->whereHas('items', function (Builder $q) use ($productId, $sellerId) {
                $q->where('product_id', $productId);

                if ($sellerId) {
                    $q->where('seller_id', $sellerId);
                }
            });

        return $query->exists();
    }

    /**
     * Полная синхронизация media (для create).
     */
    private function syncMedia(ProductComment $comment, array $images, ?string $youtubeUrl): void
    {
        // create-поток: просто добавляем media
        $this->appendMedia($comment, $images, $youtubeUrl);
    }

    /**
     * Добавление media с ограничениями:
     * - максимум 5 изображений
     * - максимум 1 youtube
     */
    private function appendMedia(ProductComment $comment, array $images, ?string $youtubeUrl): void
    {
        $currentImagesCount = ProductCommentMedia::query()
            ->where('comment_id', $comment->id)
            ->where('type', 'image')
            ->count();

        $incomingImagesCount = count($images);

        if (($currentImagesCount + $incomingImagesCount) > 5) {
            throw ValidationException::withMessages([
                'images' => 'Максимум 5 фото на один коментар.',
            ]);
        }

        $hasYoutube = ProductCommentMedia::query()
            ->where('comment_id', $comment->id)
            ->where('type', 'youtube')
            ->exists();

        if ($youtubeUrl && $hasYoutube) {
            throw ValidationException::withMessages([
                'youtube_url' => 'Дозволено лише 1 YouTube-посилання на коментар.',
            ]);
        }

        $maxSort = (int) ProductCommentMedia::query()
            ->where('comment_id', $comment->id)
            ->max('sort_order');

        // Images -> existing webp processor hook
        foreach ($images as $image) {
            $maxSort++;

            // IMPORTANT:
            // Підключіть вашу існуючу функцію конвертації в webp тут.
            // Наприклад:
            // $path = app(\App\Services\ImageService::class)->storeAsWebp($image, 'comment-media');
            // Ниже fallback без конвертации (замените на ваш метод):
            $path = $this->storeImageAsWebpViaExistingPipeline($image);

            ProductCommentMedia::create([
                'comment_id' => $comment->id,
                'type' => 'image',
                'path' => $path,
                'external_url' => null,
                'sort_order' => $maxSort,
            ]);
        }

        if ($youtubeUrl) {
            $normalized = $this->normalizeYoutubeUrl($youtubeUrl);
            $maxSort++;

            ProductCommentMedia::create([
                'comment_id' => $comment->id,
                'type' => 'youtube',
                'path' => null,
                'external_url' => $normalized,
                'sort_order' => $maxSort,
            ]);
        }
    }

    /**
     * TODO: замініть на вашу реальну існуючу функцію конвертації в webp.
     */
    private function storeImageAsWebpViaExistingPipeline($uploadedFile): string
    {
        // ВАЖНО: здесь нужно подключить уже существующую функцию в вашем проекте.
        // Временный fallback:
        return $uploadedFile->store('product-comments', 'public');
    }

    private function normalizeYoutubeUrl(string $url): string
    {
        $url = trim($url);

        if (!preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/i', $url)) {
            throw ValidationException::withMessages([
                'youtube_url' => 'Дозволені тільки youtube.com або youtu.be.',
            ]);
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        return $url;
    }
}