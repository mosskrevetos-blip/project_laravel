<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'author_id',
        'parent_id',
        'root_id',
        'type', // review|question|answer
        'rating',
        'body',
        'pros',
        'cons',
        'is_verified_purchase',
        'answer_origin', // administration|seller|null
        'moderation_status', // pending|approved|rejected
        'moderation_reject_reason',
        'moderated_by',
        'moderated_at',
        'edited_by_admin_id',
        'deleted_by_admin_id',
    ];

    protected $casts = [
        'is_verified_purchase' => 'boolean',
        'moderated_at' => 'datetime',
        'rating' => 'integer',
    ];

    protected $appends = [
        'likes_count',
        'dislikes_count',
        'helpfulness_score',
        'has_answers',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function root()
    {
        return $this->belongsTo(self::class, 'root_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function answers()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('type', 'answer')
            ->where('moderation_status', 'approved')
            ->orderBy('created_at');
    }

    public function media()
    {
        return $this->hasMany(ProductCommentMedia::class, 'comment_id')
            ->orderBy('sort_order');
    }

    public function images()
    {
        return $this->hasMany(ProductCommentMedia::class, 'comment_id')
            ->where('type', 'image')
            ->orderBy('sort_order');
    }

    public function youtube()
    {
        return $this->hasOne(ProductCommentMedia::class, 'comment_id')
            ->where('type', 'youtube');
    }

    public function reactions()
    {
        return $this->hasMany(ProductCommentReaction::class, 'comment_id');
    }

    public function likes()
    {
        return $this->hasMany(ProductCommentReaction::class, 'comment_id')
            ->where('reaction', 'like');
    }

    public function dislikes()
    {
        return $this->hasMany(ProductCommentReaction::class, 'comment_id')
            ->where('reaction', 'dislike');
    }

    public function reports()
    {
        return $this->hasMany(ProductCommentReport::class, 'comment_id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function editedByAdmin()
    {
        return $this->belongsTo(User::class, 'edited_by_admin_id');
    }

    public function deletedByAdmin()
    {
        return $this->belongsTo(User::class, 'deleted_by_admin_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getLikesCountAttribute(): int
    {
        if (array_key_exists('likes_count', $this->attributes)) {
            return (int) $this->attributes['likes_count'];
        }

        return (int) $this->reactions()->where('reaction', 'like')->count();
    }

    public function getDislikesCountAttribute(): int
    {
        if (array_key_exists('dislikes_count', $this->attributes)) {
            return (int) $this->attributes['dislikes_count'];
        }

        return (int) $this->reactions()->where('reaction', 'dislike')->count();
    }

    public function getHelpfulnessScoreAttribute(): int
    {
        return $this->likes_count - $this->dislikes_count;
    }

    public function getHasAnswersAttribute(): bool
    {
        if (array_key_exists('answers_count', $this->attributes)) {
            return ((int) $this->attributes['answers_count']) > 0;
        }

        return $this->children()
            ->where('type', 'answer')
            ->where('moderation_status', 'approved')
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeType(Builder $query, ?string $type): Builder
    {
        if (!$type) return $query;
        return $query->where('type', $type);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('moderation_status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('moderation_status', 'pending');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('moderation_status', 'rejected');
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeReviews(Builder $query): Builder
    {
        return $query->where('type', 'review');
    }

    public function scopeQuestions(Builder $query): Builder
    {
        return $query->where('type', 'question');
    }

    public function scopeAnswers(Builder $query): Builder
    {
        return $query->where('type', 'answer');
    }

    public function scopeWithEngagementStats(Builder $query): Builder
    {
        return $query->withCount([
            'reactions as likes_count' => function ($q) {
                $q->where('reaction', 'like');
            },
            'reactions as dislikes_count' => function ($q) {
                $q->where('reaction', 'dislike');
            },
            'children as answers_count' => function ($q) {
                $q->where('type', 'answer')
                    ->where('moderation_status', 'approved');
            },
        ]);
    }

    public function scopeWithPublicRelations(Builder $query): Builder
    {
        return $query->with([
            'author:id,name',
            'media:id,comment_id,type,path,external_url,sort_order',
            'answers.author:id,name',
            'answers.media:id,comment_id,type,path,external_url,sort_order',
        ]);
    }

    /**
     * Для автора показываем его own rejected/pending, а для остальных — только approved.
     */
    public function scopeVisibleForUser(Builder $query, ?int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('moderation_status', 'approved');

            if ($userId) {
                $q->orWhere(function (Builder $my) use ($userId) {
                    $my->where('author_id', $userId)
                        ->whereIn('moderation_status', ['pending', 'rejected']);
                });
            }
        });
    }

    public function scopeSortReviews(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'date_asc'      => $query->orderBy('created_at'),
            'rating_desc'   => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'rating_asc'    => $query->orderBy('rating')->orderByDesc('created_at'),
            'helpful_desc'  => $query->orderByRaw('(COALESCE(likes_count,0) - COALESCE(dislikes_count,0)) DESC')
                                     ->orderByDesc('created_at'),
            'helpful_asc'   => $query->orderByRaw('(COALESCE(likes_count,0) - COALESCE(dislikes_count,0)) ASC')
                                     ->orderByDesc('created_at'),
            default         => $query->orderByDesc('created_at'),
        };
    }

    public function scopeSortQuestions(Builder $query, ?string $sort): Builder
    {
        // сначала с ответами, потом сорт внутри
        $query->orderByDesc('answers_count');

        return match ($sort) {
            'date_asc'      => $query->orderBy('created_at'),
            'helpful_desc'  => $query->orderByRaw('(COALESCE(likes_count,0) - COALESCE(dislikes_count,0)) DESC')
                                     ->orderByDesc('created_at'),
            'helpful_asc'   => $query->orderByRaw('(COALESCE(likes_count,0) - COALESCE(dislikes_count,0)) ASC')
                                     ->orderByDesc('created_at'),
            default         => $query->orderByDesc('created_at'),
        };
    }
}