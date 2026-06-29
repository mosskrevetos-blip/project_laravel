<?php

namespace App\Policies;

use App\Models\ProductCommentReport;
use App\Models\User;

class ProductCommentReportPolicy
{
    /**
     * Список тікетів скарг (адмін/менеджер).
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrManager($user);
    }

    /**
     * Перегляд конкретного тікета (адмін/менеджер).
     */
    public function view(User $user, ProductCommentReport $report): bool
    {
        return $this->isAdminOrManager($user);
    }

    /**
     * Обробка скарги (resolved/rejected) (адмін/менеджер).
     */
    public function resolve(User $user, ProductCommentReport $report): bool
    {
        return $this->isAdminOrManager($user);
    }

    private function isAdminOrManager(User $user): bool
    {
        return method_exists($user, 'hasRole')
            ? ($user->hasRole('admin') || $user->hasRole('manager'))
            : false;
    }
}