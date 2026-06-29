<?php

namespace App\Providers;

// Важно! Не забудь импортировать свои классы
use App\Models\Attribute;
use App\Policies\AttributePolicy;
use App\Models\Product;
use App\Policies\ProductPolicy;
use App\Models\Category;
use App\Policies\CategoryPolicy;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Models\Order;
use App\Models\ProductComment;
use App\Policies\ProductCommentPolicy;
use App\Models\ProductCommentReport;
use App\Policies\ProductCommentReportPolicy;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 👇 Добавь вот эту строку
        Product::class => ProductPolicy::class,
        Category::class => CategoryPolicy::class,
        User::class => UserPolicy::class,
        Order::class => OrderPolicy::class,
        Attribute::class => AttributePolicy::class,
        ProductComment::class => ProductCommentPolicy::class,
        ProductCommentReport::class => ProductCommentReportPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/{$token}?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}