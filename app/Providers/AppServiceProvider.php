<?php

namespace App\Providers;

use App\Filament\Auth\LoginResponse;
use App\Models\Transaction;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Support\Access;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // The Role and Permission models come from the vendor package, so the
        // conventional `App\Policies\{Model}Policy` discovery can not find the
        // policies by name. Register them explicitly.
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        // Full access still respects protected records and transaction locks.
        Gate::before(function ($user, string $ability, array $arguments) {
            $record = $arguments[0] ?? null;
            if ($ability === 'delete' && $record instanceof Role && $record->name === Access::MASTER_ROLE) {
                return false;
            }

            if ($record instanceof Transaction && in_array($ability, ['update', 'delete'], true)) {
                return null;
            }

            return $user->isMaster() ? true : null;
        });
    }
}
