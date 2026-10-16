<?php

/*
 * ronu/rest-permissions. Until rest-generic-class 2.x these keys lived in config/rest-generic-class.php
 * under `permissions`; that block is still honoured while this file is not published (deprecated).
 * Eager-load authorization (?relations=) is a core setting: rest-generic-class.authorization.*.
 */
return [
    'authorization' => [
        // null preserves legacy guard-prefix matching; [] matches no routes.
        'route_prefixes' => env('REST_AUTHZ_ROUTE_PREFIXES') === null ? null : array_values(array_filter(array_map(
            'trim', explode(',', env('REST_AUTHZ_ROUTE_PREFIXES', ''))
        ), static fn ($prefix) => $prefix !== '')),
        'naming' => env('REST_AUTHZ_NAMING', 'derived'),
        // Legacy fail-open is deprecated; use deny for protected routes.
        'on_unresolved' => env('REST_AUTHZ_ON_UNRESOLVED', 'allow'),
        'register_gate' => env('REST_AUTHZ_REGISTER_GATE', false),
        'gate_guard' => env('REST_AUTHZ_GATE_GUARD'),
        // false: module-based globals; true: only permission_global action attributes.
        'explicit_globals' => env('REST_AUTHZ_EXPLICIT_GLOBALS', false),
        // Catalog only: null includes all scoped routes; [] includes none.
        'catalog_middleware' => env('REST_AUTHZ_CATALOG_MIDDLEWARE') === null ? null : array_values(array_filter(array_map(
            'trim', explode(',', env('REST_AUTHZ_CATALOG_MIDDLEWARE', ''))
        ), static fn ($marker) => $marker !== '')),
    ],

    // Name of the Eloquent relation on the User model that returns its roles.
    // This is the global fallback; each User model may override it by declaring
    // `const ROLES_RELATION = '...'`. Default 'roles' keeps backward compatibility.
    // The relation may be one-to-many (BelongsTo/HasOne -> single Role) or
    // many-to-many (BelongsToMany/HasMany -> Collection of Roles); the trait
    // HasReadableUserPermissions normalizes either into a Collection.
    'roles_relation' => env('REST_PERMISSIONS_ROLES_RELATION', 'roles'),

    'routes' => [
        'enabled' => env('REST_PERMISSIONS_ROUTES_ENABLED', false),
        'prefix' => env('REST_PERMISSIONS_ROUTES_PREFIX', 'permissions'),
        'middleware' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('REST_PERMISSIONS_ROUTES_MIDDLEWARE', 'api,auth:api'))
        ))),
        'guard' => env('REST_PERMISSIONS_ROUTES_GUARD', 'api'),
    ],

    // Optional declarative contract enforcement.
    // If declared, the service provider verifies at boot that the FQCN implements the
    // required contract (fail-fast at startup). If left null, contracts are still
    // enforced lazily by UserRolesResolver on first use.
    // - user_model MUST implement Ronu\RestPermissions\Support\Permissions\Contracts\ProvidesRoles
    // - role_model MUST implement Ronu\RestPermissions\Support\Permissions\Contracts\ProvidesRolePermissions
    'contracts' => [
        'user_model' => env('REST_PERMISSIONS_USER_MODEL'),
        'role_model' => env('REST_PERMISSIONS_ROLE_MODEL'),
    ],
];
