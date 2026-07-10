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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductCommentService
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly FileService $fileService
    ) {}

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
            ->with([
                'product:id,title,slug',
                'author:id,name',
                'media:id,comment_id,type,path,external_url,sort_order',
                'media.comment:id,product_id',
                'answers.author:id,name',
                'answers.media:id,comment_id,type,path,external_url,sort_order',
                'answers.media.comment:id,product_id',
            ]);

        if ($viewerId) {
            $query->withExists([
                'reports as my_pending_report_exists' => function ($q) use ($viewerId) {
                    $q->where('reporter_id', $viewerId)->where('status', 'pending');
                }
            ]);
        }

        if ($type === 'review' && !empty($filters['rating'])) {
            $query->where('rating', (int)$filters['rating']);
        }

        if (isset($filters['verified']) && (int)$filters['verified'] === 1) {
            $query->where('is_verified_purchase', true);
        }

        if (isset($filters['with_photos']) && (int)$filters['with_photos'] === 1) {
            $query->whereHas('media', fn ($q) => $q->where('type', 'image'));
        }

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
     * Список root-коментарів поточного автора.
     */
    public function listForAuthor(int $authorId, array $filters = []): array
    {
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $perPage = (int)($filters['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 100));

        $query = ProductComment::query()
            ->where('author_id', $authorId)
            ->whereNull('parent_id')
            ->withEngagementStats()
            ->with([
                'product:id,title,slug',
                'author:id,name',
                'media:id,comment_id,type,path,external_url,sort_order',
                'media.comment:id,product_id',
                'answers.author:id,name',
                'answers.media:id,comment_id,type,path,external_url,sort_order',
                'answers.media.comment:id,product_id',
            ])
            ->orderByDesc('created_at');

        if ($type && in_array($type, ['review', 'question'], true)) {
            $query->where('type', $type);
        }

        if ($status && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('moderation_status', $status);
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

            $comment->root_id = $comment->id;
            $comment->save();

            $this->syncMedia($comment, $images, $payload['youtube_url'] ?? null);

            return $comment->fresh([
                'product:id,title,slug',
                'author:id,name',
                'media:id,comment_id,type,path,external_url,sort_order',
                'media.comment:id,product_id',
            ]);
        });
    }

    public function createAnswer(ProductComment $rootComment, User $actor, array $payload, array $images = []): ProductComment
    {
        if ($rootComment->parent_id !== null) {
            throw ValidationException::withMessages(['comment' => 'Відповідати можна тільки на кореневий коментар.']);
        }

        if (!in_array($rootComment->type, ['review', 'question'], true)) {
            throw ValidationException::withMessages(['comment' => 'Відповідь можлива тільки для відгуку або питання.']);
        }

        return DB::transaction(function () use ($rootComment, $actor, $payload, $images) {
            $product = $rootComment->product()->firstOrFail();
            $isAdminOrManager = method_exists($actor, 'hasRole')
                ? ($actor->hasRole('admin') || $actor->hasRole('manager'))
                : false;

            $isSellerOwner = ((int)($product->user_id ?? 0) === (int)$actor->id);

            if (!$isAdminOrManager && !$isSellerOwner) {
                throw ValidationException::withMessages(['comment' => 'Недостатньо прав для відповіді.']);
            }

            $answer = new ProductComment();
            $answer->product_id = $rootComment->product_id;
            $answer->author_id = $actor->id;
            $answer->parent_id = $rootComment->id;
            $answer->root_id = $rootComment->id;
            $answer->type = 'answer';
            $answer->body = $payload['body'] ?? null;
            $answer->answer_origin = $isAdminOrManager ? 'administration' : 'seller';
            $answer->moderation_status = 'approved';
            $answer->save();

            $this->syncMedia($answer, $images, $payload['youtube_url'] ?? null);

            return $answer->fresh([
                'author:id,name',
                'media:id,comment_id,type,path,external_url,sort_order',
                'media.comment:id,product_id',
            ]);
        });
    }

    public function upsertReaction(ProductComment $comment, User $user, string $reaction): array
    {
        if (!in_array($reaction, ['like', 'dislike'], true)) {
            throw ValidationException::withMessages(['reaction' => 'Дозволені значення: like або dislike.']);
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

            if ($row->reaction !== $reaction) {
                $row->reaction = $reaction;
                $row->save();
            }
        });

        $likes = ProductCommentReaction::where('comment_id', $comment->id)->where('reaction', 'like')->count();
        $dislikes = ProductCommentReaction::where('comment_id', $comment->id)->where('reaction', 'dislike')->count();

        return [
            'comment_id' => $comment->id,
            'likes_count' => (int)$likes,
            'dislikes_count' => (int)$dislikes,
            'helpfulness_score' => (int)$likes - (int)$dislikes,
            'my_reaction' => $reaction,
        ];
    }

    public function createReport(ProductComment $comment, User $reporter, string $reason): ProductCommentReport
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Причина скарги обов’язкова.']);
        }

        return DB::transaction(function () use ($comment, $reporter, $reason) {
            $existsPending = ProductCommentReport::query()
                ->where('comment_id', $comment->id)
                ->where('reporter_id', $reporter->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->exists();

            if ($existsPending) {
                throw ValidationException::withMessages(['reason' => 'У вас вже є активна скарга на цей коментар.']);
            }

            return ProductCommentReport::create([
                'comment_id' => $comment->id,
                'reporter_id' => $reporter->id,
                'reason' => $reason,
                'status' => 'pending',
            ]);
        });
    }

    public function moderateComment(ProductComment $comment, User $moderator, string $status, ?string $rejectReason = null): ProductComment
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['moderation_status' => 'Дозволені статуси: approved або rejected.']);
        }

        if ($comment->parent_id !== null) {
            throw ValidationException::withMessages(['comment' => 'Модерація застосовується тільки до кореневих коментарів.']);
        }

        if (!in_array($comment->type, ['review', 'question'], true)) {
            throw ValidationException::withMessages(['comment' => 'Модерація доступна тільки для review/question.']);
        }

        if ($status === 'rejected' && trim((string)$rejectReason) === '') {
            throw ValidationException::withMessages(['moderation_reject_reason' => 'Для відхилення потрібно вказати причину.']);
        }

        $comment->moderation_status = $status;
        $comment->moderation_reject_reason = $status === 'rejected' ? trim((string)$rejectReason) : null;
        $comment->moderated_by = $moderator->id;
        $comment->moderated_at = now();
        $comment->save();

        return $comment->fresh(['author:id,name']);
    }

    /**
     * ADMIN UPDATE:
     * - text fields
     * - remove_media_ids[] (legacy)
     * - media_sync[] (full sync)
     * - append images[]
     * - append youtube_url
     */
    public function adminUpdateComment(ProductComment $comment, User $admin, array $payload, array $images = []): ProductComment
    {
        return DB::transaction(function () use ($comment, $admin, $payload, $images) {
            // --- text fields ---
            if (array_key_exists('body', $payload)) {
                $comment->body = $payload['body'];
            }

            if ($comment->type === 'review') {
                if (array_key_exists('pros', $payload)) $comment->pros = $payload['pros'];
                if (array_key_exists('cons', $payload)) $comment->cons = $payload['cons'];
                if (array_key_exists('rating', $payload)) $comment->rating = $payload['rating'];
            }

            $comment->edited_by_admin_id = $admin->id;
            $comment->save();

            // --- remove_media_ids (legacy) ---
            $removeIds = collect($payload['remove_media_ids'] ?? [])
                ->map(fn ($id) => (int)$id)
                ->unique()
                ->values();

            if ($removeIds->isNotEmpty()) {
                $toDelete = ProductCommentMedia::query()
                    ->where('comment_id', $comment->id)
                    ->whereIn('id', $removeIds)
                    ->get();

                foreach ($toDelete as $media) {
                    if ($media->type === 'image' && $media->path) {
                        $this->deleteCommentImageVariantsByBasename((int)$comment->product_id, (string)$media->path);
                    }
                    $media->delete();
                }
            }

            // --- media_sync (full sync) ---
            if (!empty($payload['media_sync']) && is_array($payload['media_sync'])) {
                $this->applyMediaSync($comment, collect($payload['media_sync']));
            }

            // --- append newly uploaded images / youtube ---
            if (!empty($images) || !empty($payload['youtube_url'])) {
                $this->appendMedia($comment, $images, $payload['youtube_url'] ?? null);
            }

            return $comment->fresh([
                'product:id,title,slug',
                'author:id,name',
                'media:id,comment_id,type,path,external_url,sort_order',
                'media.comment:id,product_id',
                'answers.author:id,name',
                'answers.media:id,comment_id,type,path,external_url,sort_order',
                'answers.media.comment:id,product_id',
            ]);
        });
    }

    public function adminDeleteComment(ProductComment $comment, User $admin): void
    {
        DB::transaction(function () use ($comment, $admin) {
            $comment->deleted_by_admin_id = $admin->id;
            $comment->save();

            $media = ProductCommentMedia::query()->where('comment_id', $comment->id)->get();
            foreach ($media as $m) {
                if ($m->type === 'image' && $m->path) {
                    $this->deleteCommentImageVariantsByBasename((int)$comment->product_id, (string)$m->path);
                }
            }

            ProductCommentMedia::query()->where('comment_id', $comment->id)->delete();
            $comment->delete();
        });
    }

    public function resolveReport(ProductCommentReport $report, User $resolver, string $status, ?string $resolutionNote = null): ProductCommentReport
    {
        if (!in_array($status, ['resolved', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Дозволені статуси: resolved або rejected.']);
        }

        $report->status = $status;
        $report->resolution_note = $resolutionNote;
        $report->resolved_by = $resolver->id;
        $report->resolved_at = now();
        $report->save();

        return $report->fresh(['reporter:id,name', 'resolver:id,name']);
    }

    private function resolveVerifiedPurchase(int $userId, int $productId, ?int $sellerId = null): bool
    {
        $query = Order::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['paid', 'completed', 'delivered'])
            ->whereHas('products', function (Builder $q) use ($productId) {
                $q->where('products.id', $productId);
            });

        if ($sellerId) $query->where('seller_id', $sellerId);

        return $query->exists();
    }

    private function syncMedia(ProductComment $comment, array $images, ?string $youtubeUrl): void
    {
        $this->appendMedia($comment, $images, $youtubeUrl);
    }

    /**
     * Append mode:
     * - add images up to limit 5
     * - add only one youtube per comment
     */
    private function appendMedia(ProductComment $comment, array $images, ?string $youtubeUrl): void
    {
        $currentImagesCount = ProductCommentMedia::query()
            ->where('comment_id', $comment->id)
            ->where('type', 'image')
            ->count();

        $incomingImagesCount = count($images);

        if (($currentImagesCount + $incomingImagesCount) > 5) {
            throw ValidationException::withMessages(['images' => 'Максимум 5 фото на один коментар.']);
        }

        $hasYoutube = ProductCommentMedia::query()
            ->where('comment_id', $comment->id)
            ->where('type', 'youtube')
            ->exists();

        if ($youtubeUrl && $hasYoutube) {
            throw ValidationException::withMessages(['youtube_url' => 'Дозволено лише 1 YouTube-посилання на коментар.']);
        }

        $maxSort = (int) ProductCommentMedia::query()
            ->where('comment_id', $comment->id)
            ->max('sort_order');

        foreach ($images as $image) {
            $maxSort++;
            $basename = $this->storeImageAsWebpViaExistingPipeline($image, (int)$comment->product_id);

            ProductCommentMedia::create([
                'comment_id' => $comment->id,
                'type' => 'image',
                'path' => $basename,
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
     * Full sync mode:
     * - remove media that are not in sync list (for existing IDs)
     * - keep/reorder existing
     * - allow creating one new youtube from sync item with id=null
     */
    private function applyMediaSync(ProductComment $comment, Collection $sync): void
    {
        $normalized = $sync
            ->map(function ($item, $idx) {
                return [
                    'id' => isset($item['id']) && $item['id'] !== null ? (int)$item['id'] : null,
                    'type' => $item['type'] ?? null,
                    'url' => $item['url'] ?? null,
                    'external_url' => $item['external_url'] ?? null,
                    'sort_order' => isset($item['sort_order']) ? (int)$item['sort_order'] : (int)$idx,
                ];
            })
            ->values();

        $existing = ProductCommentMedia::query()
            ->where('comment_id', $comment->id)
            ->get()
            ->keyBy('id');

        $keepIds = $normalized
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int)$id)
            ->unique()
            ->values();

        // delete omitted existing media
        $toDelete = ProductCommentMedia::query()
            ->where('comment_id', $comment->id)
            ->when(
                $keepIds->isNotEmpty(),
                fn ($q) => $q->whereNotIn('id', $keepIds->all()),
                fn ($q) => $q
            )
            ->get();

        foreach ($toDelete as $media) {
            if ($media->type === 'image' && $media->path) {
                $this->deleteCommentImageVariantsByBasename((int)$comment->product_id, (string)$media->path);
            }
            $media->delete();
        }

        // existing youtube count after deletion
        $youtubeCount = ProductCommentMedia::query()
            ->where('comment_id', $comment->id)
            ->where('type', 'youtube')
            ->count();

        foreach ($normalized as $row) {
            $id = $row['id'];
            $type = $row['type'];
            $sort = $row['sort_order'];

            // reorder/update existing
            if ($id && $existing->has($id)) {
                /** @var ProductCommentMedia $m */
                $m = $existing->get($id);
                $m->sort_order = $sort;

                if ($m->type === 'youtube') {
                    // allow youtube url update
                    $newUrl = $row['external_url'] ?: $row['url'];
                    if ($newUrl) {
                        $m->external_url = $this->normalizeYoutubeUrl($newUrl);
                    }
                }

                $m->save();
                continue;
            }

            // create new youtube from sync row if id=null
            if (!$id && $type === 'youtube') {
                if ($youtubeCount >= 1) {
                    throw ValidationException::withMessages([
                        'media_sync' => 'Дозволено лише 1 YouTube-посилання на коментар.',
                    ]);
                }

                $newUrl = $row['external_url'] ?: $row['url'];
                if (!$newUrl) {
                    throw ValidationException::withMessages([
                        'media_sync' => 'Для нового YouTube потрібно передати url/external_url.',
                    ]);
                }

                ProductCommentMedia::create([
                    'comment_id' => $comment->id,
                    'type' => 'youtube',
                    'path' => null,
                    'external_url' => $this->normalizeYoutubeUrl($newUrl),
                    'sort_order' => $sort,
                ]);

                $youtubeCount++;
            }
        }
    }

    private function storeImageAsWebpViaExistingPipeline($uploadedFile, int $productId): string
    {
        $basename = $this->fileService->generateUniqueFilename($uploadedFile);

        $this->imageService->generateVariantsAndManifest(
            uploadedFile: $uploadedFile,
            productId: $productId,
            basename: $basename
        );

        return $basename;
    }

    private function deleteCommentImageVariantsByBasename(int $productId, string $basename): void
    {
        $disk = Storage::disk('public');
        $dir = "products/{$productId}";

        $nameWithoutExt = pathinfo($basename, PATHINFO_FILENAME);
        $ext = pathinfo($basename, PATHINFO_EXTENSION) ?: 'webp';

        foreach ([150, 400, 800, 1200, 2000] as $size) {
            $disk->delete("{$dir}/{$nameWithoutExt}_{$size}.webp");
            $disk->delete("{$dir}/{$nameWithoutExt}_{$size}.{$ext}");
        }

        $disk->delete("{$dir}/{$basename}");
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