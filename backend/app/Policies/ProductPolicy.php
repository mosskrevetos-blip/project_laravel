<?php


namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
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
     * Все могут просматривать список товаров.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Все могут просматривать один товар.
     */
    public function view(?User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Створювати товари можуть тільки користувачі з ролями - 'manager', 'user', 'wholesaler','manufacturer'.
     */
    public function create(User $user): bool
    {
        // Визначаємо список ролей, яким дозволено створювати товари.
        $allowedRoles = [
            'manager', 
            'user', 
            'wholesaler', 
            'manufacturer'
        ];
        // Перевіряємо, чи є у користувача хоча б одна з цих ролей.
        // Метод whereIn() дуже ефективно робить це одним запитом до БД.
        return $user->roles()->whereIn('slug', $allowedRoles)->exists();
    }

    /**
     * Обновлять товар может только его владелец (продавец).
     */
    public function update(User $user, Product $product): bool
    {
        return $user->id === $product->user_id;
    }

    /**
     * Удалять товар может только его владелец (продавец).
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->id === $product->user_id;
    }
}