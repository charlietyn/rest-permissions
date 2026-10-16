<?php

namespace Ronu\RestPermissions;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Ronu\RestGenericClass\Core\Contracts\Authorization\AuthorizationContext;
use Ronu\RestPermissions\Support\Authorization\EffectivePermissionGate;
use Ronu\RestPermissions\Support\Authorization\RouteAuthorizationContext;
use Ronu\RestPermissions\Support\Permissions\Contracts\PermissionCompressorContract;
use Ronu\RestPermissions\Support\Permissions\Contracts\ProvidesRolePermissions;
use Ronu\RestPermissions\Support\Permissions\Contracts\ProvidesRoles;
use Ronu\RestPermissions\Support\Permissions\Exceptions\RolesContractViolationException;
use Ronu\RestPermissions\Support\Permissions\PermissionCompressor;
use Ronu\RestPermissions\Support\Permissions\UserRolesResolver;

/**
 * Route permissions for ronu/rest-generic-class: plugs the route authorization scope into the
 * core (AuthorizationContext), the effective-permission Gate, the permission routes and config.
 */
class RestPermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'rest-permissions');
        LegacyConfig::apply($this->app);

        $this->app->singleton(PermissionCompressorContract::class, PermissionCompressor::class);
        $this->app->singleton(UserRolesResolver::class);
        $this->app->scoped(EffectivePermissionGate::class);
        // The core registers NullAuthorizationContext with bindIf: this binding wins in any order.
        $this->app->bind(AuthorizationContext::class, RouteAuthorizationContext::class);
    }

    public function boot(): void
    {
        if (config('rest-permissions.authorization.register_gate', false)) {
            Gate::before(static fn ($user, string $ability) => app(EffectivePermissionGate::class)->allows($user, $ability));
            $this->app['events']->listen(RequestHandled::class, function (): void {
                if ($this->app->resolved(EffectivePermissionGate::class)) {
                    $this->app->make(EffectivePermissionGate::class)->clear();
                }
            });
        }

        $this->publishes([
            $this->configPath() => config_path('rest-permissions.php'),
        ], 'rest-permissions-config');

        if (config('rest-permissions.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/permissions.php');
        }

        $this->assertContractsIfDeclared();
    }

    /**
     * Optional fail-fast validation. If the integrator declared the user/role model FQCNs
     * in config, verify they implement the required contracts at boot time.
     * Otherwise the contracts are still enforced lazily by UserRolesResolver on first use.
     */
    private function assertContractsIfDeclared(): void
    {
        $userModel = config('rest-permissions.contracts.user_model');
        if ($userModel && !is_subclass_of($userModel, ProvidesRoles::class)) {
            throw RolesContractViolationException::userMissingContract($userModel);
        }

        $roleModel = config('rest-permissions.contracts.role_model');
        if ($roleModel && !is_subclass_of($roleModel, ProvidesRolePermissions::class)) {
            throw RolesContractViolationException::roleMissingContract($roleModel);
        }
    }

    private function configPath(): string
    {
        return __DIR__ . '/../config/rest-permissions.php';
    }
}
