<?php

namespace App\Providers;

use App\Models\UserManagement\Group;
use App\Models\UserManagement\User;
use App\Policies\UserManagement\GroupPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(Group::class, GroupPolicy::class);

        Gate::define('platform-admin', fn (User $user): bool => (bool) $user->is_platform_admin);
    }
}
