<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    /**
     * Администратор может всё.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return null;
    }

    /**
     * Все могут просматривать список Категорий.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Все могут просматривать одну категорию.
     */
    public function view(?User $user, Category $category): bool
    {
        return true;
    }

    /**
     * Создавать категории могут только администраторы.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Обновлять категорию могут только администраторы.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Удалять категорию могут только администраторы.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->hasRole('admin');
    }
}
