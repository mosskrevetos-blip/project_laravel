<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Супер-правило: адміністратор може все.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return null;
    }

    /**
     * Хто може переглядати список користувачів? (Адміністратор і Менеджер)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('manager');
    }

    /**
     * Хто може переглядати конкретного користувача? (Адміністратор і Менеджер)
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasRole('manager');
    }

    /**
     * Хто може створювати користувачів? (Тільки адміністратор)
     */
    public function create(User $user): bool
    {
        return false; // Адміністратор вже пройшов перевірку в before()
    }

    /**
     * Хто може оновлювати дані користувача? (Тільки адміністратор)
     */
    public function update(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Хто може видаляти користувача? (Тільки адміністратор)
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
