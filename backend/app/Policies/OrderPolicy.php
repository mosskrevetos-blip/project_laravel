<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return null;
    }

    /**
     * Кто может просматривать список заказов?
     */
    public function viewAny(User $user): bool
    {
        // Разрешаем всем авторизованным, т.к. фильтрация будет в контроллере
        return true;
    }

    /**
     * Кто может просматривать конкретный заказ?
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->hasRole('manager')) {
            return true;
        }

        // Другие пользователи могут видеть заказ, только если в нём есть их товар
        return $order->products()->where('user_id', $user->id)->exists();
    }

    /**
     * Кто может создавать заказы? (Для админки - никто)
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Кто может обновлять заказ? (Админ и Менеджер)
     */
    public function update(User $user, Order $order): bool
    {
        return $user->hasRole('manager');
    }

    /**
     * Кто может удалять заказ? (Админ и Менеджер)
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->hasRole('manager');
    }
}