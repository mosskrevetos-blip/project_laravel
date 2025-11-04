<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttributePolicy
{
    // адміністратор може все
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return null;
    }

    /**
     * Кто может просматривать список атрибутов? (Админ и Менеджер)
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('manager');
    }

    /**
     * Кто может просматривать конкретный атрибут? (Админ и Менеджер)
     */
    public function view(User $user, Attribute $attribute): bool
    {
        return $user->hasRole('manager');
    }

    /**
     * Кто может создавать атрибуты? (Админ и Менеджер)
     */
    public function create(User $user): bool
    {
        return $user->hasRole('manager');
    }

    /**
     * Кто может обновлять атрибут? (Админ и Менеджер)
     */
    public function update(User $user, Attribute $attribute): bool
    {
        return $user->hasRole('manager');
    }

    /**
     * Кто может удалять атрибут? (Админ и Менеджер)
     */
    public function delete(User $user, Attribute $attribute): bool
    {
        return $user->hasRole('manager');
    }

    /**
     * Determine whether the user can restore the model.
     */
    // public function restore(User $user, Attribute $attribute): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, Attribute $attribute): bool
    // {
    //     return false;
    // }
}
