<?php

namespace App\Providers;

use App\Actions\PermissionRegistry;
use App\Actions\PlatformSettings;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PlatformSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('access-super-admin', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin));
        Gate::define('access-water-provider', fn (User $user): bool => $user->hasRole(UserRole::WaterProvider));
        Gate::define('access-consumer', fn (User $user): bool => $user->hasRole(UserRole::Consumer));
        Gate::define('permission', fn (User $user, string $permission): bool => $user->hasPermission($permission));

        foreach (array_keys(PermissionRegistry::LABELS) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
