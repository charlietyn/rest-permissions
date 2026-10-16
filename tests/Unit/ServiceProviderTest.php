<?php

namespace Ronu\RestPermissions\Tests\Unit;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Ronu\RestGenericClass\Core\Contracts\Authorization\AuthorizationContext;
use Ronu\RestGenericClass\Core\Providers\RestGenericClassServiceProvider;
use Ronu\RestPermissions\LegacyConfig;
use Ronu\RestPermissions\RestPermissionsServiceProvider;
use Ronu\RestPermissions\Support\Authorization\RouteAuthorizationContext;
use Ronu\RestPermissions\Support\Permissions\Contracts\PermissionCompressorContract;
use Ronu\RestPermissions\Support\Permissions\UserRolesResolver;
use Ronu\RestPermissions\Tests\Unit\Fixtures\RestoresPaginationResolvers;

final class ServiceProviderTest extends TestCase
{
    use RestoresPaginationResolvers;

    /** @var list<class-string> */
    private array $providers = [RestGenericClassServiceProvider::class, RestPermissionsServiceProvider::class];

    protected function getPackageProviders($app): array
    {
        return $this->providers;
    }

    protected function setUp(): void
    {
        $this->capturePaginationResolvers();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->restorePaginationResolvers();
        }
    }

    public function testTheRouteContextReplacesTheCoreNullContextInEitherProviderOrder(): void
    {
        $this->assertInstanceOf(RouteAuthorizationContext::class, $this->app->make(AuthorizationContext::class));

        $this->providers = array_reverse($this->providers);
        $this->refreshApplication();
        $this->assertInstanceOf(RouteAuthorizationContext::class, $this->app->make(AuthorizationContext::class));
    }

    public function testDefaultsAndBindings(): void
    {
        $this->assertSame([
            'route_prefixes' => null,
            'naming' => 'derived',
            'on_unresolved' => 'allow',
            'register_gate' => false,
            'gate_guard' => null,
            'explicit_globals' => false,
            'catalog_middleware' => null,
        ], config('rest-permissions.authorization'));
        $this->assertSame('roles', config('rest-permissions.roles_relation'));
        $this->assertTrue($this->app->bound(PermissionCompressorContract::class));
        $this->assertSame($this->app->make(UserRolesResolver::class), $this->app->make(UserRolesResolver::class));
        $this->assertFalse(Route::has('rest-generic-class.permissions.authenticated'), 'routes are opt-in');
    }

    public function testTheGateAndTheRoutesAreOptIn(): void
    {
        config(['rest-permissions.authorization.register_gate' => true, 'rest-permissions.routes.enabled' => true]);
        (new RestPermissionsServiceProvider($this->app))->boot();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $user = new class {
            public function effectivePermissions(?string $guard = null): \Illuminate\Support\Collection
            {
                return collect([(object) ['name' => 'reports.export']]);
            }
        };
        $this->assertTrue(Gate::forUser($user)->allows('reports.export'));
        // 2.x route names are kept for the whole 1.x line.
        $this->assertTrue(Route::has('rest-generic-class.permissions.authenticated'));
    }

    public function testLegacyRestGenericClassPermissionsBlockIsAppliedWhenNotPublished(): void
    {
        config([
            'rest-generic-class.permissions' => [
                'authorization' => ['naming' => 'route_name', 'route_prefixes' => ['admin']],
                'routes' => ['middleware' => ['api']],
                'roles_relation' => 'groups',
            ],
        ]);
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;
            return true;
        }, E_USER_DEPRECATED);
        try {
            LegacyConfig::apply($this->app);
        } finally {
            restore_error_handler();
        }

        $this->assertSame('route_name', config('rest-permissions.authorization.naming'));
        $this->assertSame(['admin'], config('rest-permissions.authorization.route_prefixes'));
        $this->assertSame('allow', config('rest-permissions.authorization.on_unresolved'), 'unset keys keep the defaults');
        $this->assertSame(['api'], config('rest-permissions.routes.middleware'), 'lists are replaced, not merged by index');
        $this->assertSame('groups', config('rest-permissions.roles_relation'));
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('rest-permissions-config', $deprecations[0]);
    }

    public function testAPublishedRestPermissionsConfigWinsOverTheLegacyBlock(): void
    {
        $path = $this->app->configPath('rest-permissions.php');
        file_put_contents($path, "<?php return [];\n");
        try {
            config(['rest-generic-class.permissions' => ['roles_relation' => 'groups']]);
            LegacyConfig::apply($this->app);
            $this->assertSame('roles', config('rest-permissions.roles_relation'));
        } finally {
            unlink($path);
        }
    }

    public function testMergeReplacesListsAndScalarsAndRecursesIntoMaps(): void
    {
        $this->assertSame(
            ['a' => ['x' => 1, 'y' => 3], 'list' => ['c'], 'n' => null],
            LegacyConfig::merge(['a' => ['x' => 1, 'y' => 2], 'list' => ['a', 'b'], 'n' => 'v'], ['a' => ['y' => 3], 'list' => ['c'], 'n' => null])
        );
    }
}
