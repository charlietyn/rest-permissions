# Rest Permissions for Laravel

**Route-based permissions for [ronu/rest-generic-class](https://github.com/charlietyn/rest-generic-class):** a permission catalog synchronized from your routes, the channel authorization middleware, an effective-permission Gate, role/user permission readers and the Spatie permission/role models.

Until rest-generic-class 2.x this code shipped inside the CRUD package. Since rest-generic-class 3.0 the core depends on no permission package: it authorizes through its own contracts and Laravel's Gate, and this package plugs the route permissions into them.

## Requirements

| Package | Versions |
| --- | --- |
| PHP | 8.2+ (8.3+ on Laravel 13) |
| Laravel | 12.61.1+ or 13.12+ |
| ronu/rest-generic-class | ^3.0 |
| spatie/laravel-permission | ^6.21, ^7.0 or ^8.0 (Laravel 13: 6.25+, 7.2.4+ or 8.x) |
| nwidart/laravel-modules | optional, for module-aware permissions |

## Installation

```bash
composer require ronu/rest-permissions
php artisan vendor:publish --tag=rest-permissions-config   # optional: config/rest-permissions.php
```

The service provider is auto-discovered. It binds the request authorization scope into the core
(`AuthorizationContext`), so `rest-generic-class.authorization.eager_relations=require_read` works, and
registers the optional Gate and permission routes when enabled.

## What it provides

| Piece | Class |
| --- | --- |
| Authorization middleware (route name → permission, fail closed with `on_unresolved=deny`) | `Ronu\RestPermissions\Middleware\SpatieAuthorize` |
| Route → permission resolution (`permission_ability`, `permission_requires`, `permission_global`) | `Ronu\RestPermissions\Resolvers\RouteMetaResolver` |
| Catalog sync from routes (batch, `--prune`) | `HasPermissionsService::refreshPermissions()` / `RoutePermissionRefresher` |
| Role assignment, generic notation (`admin.crm.clients.*`) | `HasPermissionsService` / `TargetPermissionResolver` |
| Effective-permission Gate (`register_gate`) | `EffectivePermissionGate` |
| Readers and payloads (`GET /permissions`, compression) | `HasReadableUserPermissions`, `HasReadableRolePermissions`, `PermissionCompressor` |
| Spatie models with `BaseModel` features | `Ronu\RestPermissions\Models\SpatiePermission`, `SpatieRole` |
| Migration script to channel authorization | `vendor/ronu/rest-permissions/scripts/enable-channel-authorization.php` |

Register the middleware as you did before, with its new name:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['permission' => \Ronu\RestPermissions\Middleware\SpatieAuthorize::class]);
})
```

## Upgrading from rest-generic-class 2.x

1. `composer require ronu/rest-generic-class:^3.0 ronu/rest-permissions:^1.0`.
2. Nothing else is required to keep running: every 2.x class name (`Ronu\RestGenericClass\Core\Models\SpatiePermission`,
   `…\Middleware\SpatieAuthorize`, the traits, contracts, `PermissionsController`, …) is aliased to its new class,
   lazily and with an `E_USER_DEPRECATED` notice, for the whole 1.x line. The `permissions` block of an existing
   `config/rest-generic-class.php` is still applied while `config/rest-permissions.php` is not published, and
   the `REST_AUTHZ_*` / `REST_PERMISSIONS_*` variables keep their names. Permission route names keep their 2.x
   names (`rest-generic-class.permissions.*`).
3. Then, at your pace: replace `Ronu\RestGenericClass\Core\…` with `Ronu\RestPermissions\…` (the namespace moves
   one level up: `Core\Support\Permissions` → `Support\Permissions`, `Core\Models\SpatieRole` → `Models\SpatieRole`),
   publish `rest-permissions-config` and move the `permissions` values there (top level: `authorization`,
   `roles_relation`, `routes`, `contracts`), and move `permissions.authorization.eager_relations*` to
   `rest-generic-class.authorization.*`.

## Documentation

- [Configuration and environment variables](docs/en/configuration.md) · [Configuración](docs/es/configuration.md)
- [Permissions: architecture, contracts and scenarios](docs/en/permissions.md) · [Permisos](docs/es/permissions.md)
- [Public API (actions by ability, generic notation, catalog sync)](docs/public-api.md)
- [Migration script to channel authorization](docs/en/enable-channel-authorization-script.md) · [Script de migración](docs/es/enable-channel-authorization-script.md)
- [Changelog](CHANGELOG.md) · [rest-generic-class 2.x release notes of these features](docs/release-notes-rest-generic-class-2.x.md)

## Testing

```bash
composer install
vendor/bin/phpunit                    # SQLite
RGC_AGGREGATE_DRIVER=mysql RGC_AGGREGATE_DATABASE=rgc_permissions_test vendor/bin/phpunit --testsuite Integration
```

## License

MIT. See [LICENSE](LICENSE).
