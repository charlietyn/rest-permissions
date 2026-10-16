# Configuration

Published with `php artisan vendor:publish --tag=rest-permissions-config` to `config/rest-permissions.php`. Until rest-generic-class 2.x these keys lived in `config/rest-generic-class.php` under `permissions`; that block is still honoured (with a deprecation) while `config/rest-permissions.php` is not published. Eager-load authorization (`?relations=`) is a core setting: see the core [authorization contracts](https://github.com/charlietyn/rest-generic-class/blob/main/documentacion/doc-en/04-reference/06-authorization-contracts.md).

## Optional permission routes

The package can register reusable permission read routes. They are disabled by default so existing applications keep full control over their route table.

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `roles_relation` | string | `roles` | Name of the Eloquent relation on the User model that returns its roles. Global fallback; each model can override it with `const ROLES_RELATION`. Supports one-to-many (single role) and many-to-many (several roles) cardinality. |
| `routes.enabled` | bool | `false` | When true, loads the package permission routes. |
| `routes.prefix` | string | `permissions` | Route prefix. Add your app prefix such as `/api` in your route group or HTTP kernel, not here. |
| `routes.middleware` | array | `['api', 'auth:api']` | Middleware applied to the package routes. |
| `routes.guard` | string | `api` | Guard used to resolve the authenticated user in the optional package route. It does not filter the payload unless the request also sends `guard`. |

Registered routes when enabled:

```http
GET /permissions
GET /permissions/by-roles
GET /permissions/by-users
```

If the consuming app also defines `apiResource('permissions')`, register the specific routes above before the resource route.

## Permission contracts (User and Role)

The package requires the integrator to declare **formal contracts** on their User and Role models (see the [detailed guide](permissions.md)). This config section is **optional** and only enables the early boot-time validation; the contracts are always enforced at runtime, even if these keys are left empty.

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `contracts.user_model` | string\|null | `null` | FQCN of the User model. When set, the `ServiceProvider` verifies in `boot()` that the class implements `Ronu\RestPermissions\Support\Permissions\Contracts\ProvidesRoles`. |
| `contracts.role_model` | string\|null | `null` | FQCN of the Role model. When set, the `ServiceProvider` verifies in `boot()` that the class implements `Ronu\RestPermissions\Support\Permissions\Contracts\ProvidesRolePermissions`. |

If any of these classes does not implement the expected interface, the application fails to boot with a `RolesContractViolationException` (actionable message stating exactly which class must implement which interface).

```php
// Example
'permissions' => [
    'contracts' => [
        'user_model' => \App\Models\User::class,
        'role_model' => \App\Models\Role::class,
    ],
],
```

## Channel authorization (v2.6.0)

`eager_relations` / `eager_relations_to_one` moved to the core (`rest-generic-class.authorization.*`).

All keys below live under `authorization`.

| Key | Default | Meaning |
| --- | --- | --- |
| `route_prefixes` | `null` | Legacy: match the Laravel group prefix starting with the guard name. An array matches URI prefixes on segment boundaries (`admin` never matches `administration`); `[]` matches nothing. Nested prefixes such as `api/admin` are supported; the first matching prefix wins. |
| `naming` | `derived` | Infer `{version}.{module}.{model}.{action}` (optional version/module). `route_name` uses the exact route name, including `store`/`destroy`, without mapping or version prepending; unnamed routes cannot resolve. |
| `on_unresolved` | `allow` | `allow` continues without checking a permission. `deny` returns 403 for every unresolved route carrying the middleware, including routes outside the configured prefixes. |
| `register_gate` | `false` | Register an optional `Gate::before` for users exposing `effectivePermissions()` without Spatie's `checkPermissionTo()`. Also include global permissions in the trait's effective union. |
| `gate_guard` | `null` | Filter the optional Gate's effective permissions by this guard; `null` accepts all guards. Use `api` when the authentication guard is `api`. |
| `explicit_globals` | `false` | Phase 2: `false` preserves module-based globals (a route without a recognized module is global). `true` stores `restrict=false` only for routes whose **action** carries `'permission_global' => true` (even inside a module); every other synced permission is restricted (fail closed). |
| `catalog_middleware` | `null` | Phase 2, catalog only: `null` includes all scoped routes. An array includes only routes whose `gatherMiddleware()` contains a string starting with one of the markers (alias or FQCN); `[]` includes none. Non-string middleware entries are ignored. |

`route_prefixes` controls route selection; it does not define authentication guards. The middleware guard parameter and sync command `--guard` must remain real guard names. The obsolete `route-permissions` configuration key is no longer read. `passesFilters()` remains an overridable hook, with no built-in filter rules.

The `allow` default is retained for compatibility but deprecated and unsafe on protected routes; switching the default to `deny` is planned for the next major of ronu/rest-permissions (it stays `allow` in 1.x so upgrading does not change behaviour).

The phase 2 legacy fix restores exact v2.5.0 module matching when `route_prefixes=null`: the URI must contain more than two non-empty segments, start with the literal guard segment, and use the exact module name. `api/mod_security/users` with module `ModSecurity` produces `users.index`; `api/ModSecurity/users` produces `ModSecurity.users.index`. Snake-case matching applies only with explicit channel prefixes. The version and controllerless-route alignment introduced in v2.6.0 remains unchanged.

`explicit_globals` controls writes during synchronization, not the reader or Gate. Declare `permission_global` as a route group attribute or with `setAction()`; never with `->defaults()`, which Laravel turns into a route parameter passed to controllers (and the resolver ignores). Resynchronizing updates `restrict` on existing selected rows. The optional Gate still grants persisted `notRestricted()` permissions to every authenticated user of its guard. `catalog_middleware` does not change runtime authorization, expand aliases/groups or delete existing rows: remove obsolete permissions explicitly after reviewing assignments. Excluded rows are not updated, including their previous global status.

```php
// config/rest-permissions.php
'authorization' => [
    'route_prefixes' => ['admin', 'client', 'mobile'],
    'naming' => 'route_name',
    'on_unresolved' => 'deny',
    'register_gate' => true,
    'gate_guard' => 'api',
    'explicit_globals' => true,
    'catalog_middleware' => ['maybe.permission', \Ronu\RestPermissions\Middleware\SpatieAuthorize::class],
],
```

```php
// routes/admin.php: group attribute, copied into every route action
Route::group(['as' => 'admin.', 'permission_global' => true], function () {
    Route::get('auth/permissions', [AuthController::class, 'permissions'])->name('auth.permissions');
    Route::get('auth/user-profile', [AuthController::class, 'profile'])->name('auth.user-profile');
});

// a single route outside a group
$route = Route::get('auth/modules', [AuthController::class, 'modules'])->name('admin.auth.modules');
$route->setAction([...$route->getAction(), 'permission_global' => true]);
```

## Environment variables

| Variable | Default | Used for |
| --- | --- | --- |
| `REST_PERMISSIONS_ROLES_RELATION` | `roles` | Default name of the User model relation that returns its roles. Each model can override it with `const ROLES_RELATION`. |
| `REST_PERMISSIONS_ROUTES_ENABLED` | `false` | Enables package-provided permission read routes. |
| `REST_PERMISSIONS_ROUTES_PREFIX` | `permissions` | Prefix for optional permission routes. |
| `REST_PERMISSIONS_ROUTES_MIDDLEWARE` | `api,auth:api` | Comma-separated middleware list for optional permission routes. |
| `REST_PERMISSIONS_ROUTES_GUARD` | `api` | Guard used to resolve the authenticated user in optional permission routes. |
| `REST_PERMISSIONS_USER_MODEL` | _(unset)_ | User model FQCN used for early boot validation of the `ProvidesRoles` contract. Optional; when unset the validation runs lazily at runtime. |
| `REST_PERMISSIONS_ROLE_MODEL` | _(unset)_ | Role model FQCN used for early boot validation of the `ProvidesRolePermissions` contract. Optional; when unset the validation runs lazily at runtime. |
| `REST_AUTHZ_ROUTE_PREFIXES` | unset (`null`) | Comma-separated prefixes; whitespace is trimmed. An empty value yields `[]`. |
| `REST_AUTHZ_NAMING` | `derived` | `derived` or `route_name`. |
| `REST_AUTHZ_ON_UNRESOLVED` | `allow` | `allow` or `deny`; use `deny` on protected routes. |
| `REST_AUTHZ_REGISTER_GATE` | `false` | Enable the effective-permission Gate and global union. |
| `REST_AUTHZ_GATE_GUARD` | unset (`null`) | Optional guard filter, for example `api`. |
| `REST_AUTHZ_EXPLICIT_GLOBALS` | `false` | Phase 2: `true` makes only routes marked with the `permission_global` action attribute global; `false` preserves globals for routes without a module. |
| `REST_AUTHZ_CATALOG_MIDDLEWARE` | unset (`null`) | Phase 2: comma-separated middleware markers, aliases or FQCNs. Unset includes all scoped routes; an empty value yields `[]` (no catalog routes). |

Prefix and middleware lists trim whitespace, discard empty entries and retain values such as `0`. `null` and an empty list have different meanings. Example:

```dotenv
REST_AUTHZ_EXPLICIT_GLOBALS=true
REST_AUTHZ_CATALOG_MIDDLEWARE=maybe.permission
```

Refresh cached configuration, then synchronize permissions to apply these options. Changing the environment alone does not update stored restrictions or remove obsolete permissions.


[Back to the README](../../README.md)
