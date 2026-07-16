<?php

namespace App\Policies;

use App\Models\ProductComment;
use App\Models\User;

class ProductCommentPolicy
{
    /**
     * Модерація root-коментарів (review/question) admin/manager.
     */
    public function moderate(User $user, ProductComment $comment): bool
    {
        if (!$this->isAdminOrManager($user)) {
            return false;
        }

        // По логике модерации: модерируем только root review/question
        if (!is_null($comment->parent_id)) {
            return false;
        }

        return in_array($comment->type, ['review', 'question'], true);
    }

    /**
     * Редагування коментарів адміністрацією.
     */
    public function updateByAdmin(User $user, ProductComment $comment): bool
    {
        return $this->isAdminOrManager($user);
    }

    /**
     * Видалення коментарів адміністрацією.
     */
    public function deleteByAdmin(User $user, ProductComment $comment): bool
    {
        return $this->isAdminOrManager($user);
    }

    /**
     * (Опционально) перегляд черги модерації коментарів.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrManager($user);
    }

    /**
     * (Опционально) перегляд конкретного коментаря в адмінці.
     */
    public function view(User $user, ProductComment $comment): bool
    {
        return $this->isAdminOrManager($user);
    }

    private function isAdminOrManager(User $user): bool
    {
        // Под ваш текущий authStore / backend role helper
        return method_exists($user, 'hasRole')
            ? ($user->hasRole('admin') || $user->hasRole('manager'))
            : false;
    }

    public function restoreByAdmin(User $user, ProductComment $comment): bool
    {
        return $this->isAdminOrManager($user);
    }
}