<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\UserLoginHistory::class => \App\Policies\UserLoginHistoryPolicy::class,
        \App\Modules\IncidentManagement\Models\Incident::class => \App\Policies\IncidentPolicy::class,
    ];

    public function register(): void
    {
        parent::register();

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            // Superadmins bypass everything
            if (method_exists($user, 'isGlobalSuperAdmin') && $user->isGlobalSuperAdmin()) {
                return true;
            }

            // Entitlement Bridge: Check if the tenant is entitled to this permission
            /** @var \App\Services\Tenancy\EntitlementService $entitlementService */
            $entitlementService = app(\App\Services\Tenancy\EntitlementService::class);
            if (! $entitlementService->isEntitled($ability)) {
                return false;
            }

            return null;
        });
    }

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        \Illuminate\Support\Facades\Gate::define('access-superadmin-dashboard', function ($user) {
            return \Illuminate\Support\Facades\Cache::remember("user_{$user->id}_is_superadmin", 3600, function () use ($user) {
                return \Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_id', $user->id)
                    ->where('model_has_roles.model_type', get_class($user))
                    ->where('roles.name', 'Superadmin')
                    ->whereNull('model_has_roles.tenant_id')
                    ->exists();
            });
        });
    }
}
