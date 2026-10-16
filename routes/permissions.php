<?php

use Illuminate\Support\Facades\Route;
use Ronu\RestPermissions\Controllers\PermissionsController;

$prefix = config('rest-permissions.routes.prefix', 'permissions');
$middleware = config('rest-permissions.routes.middleware', ['api', 'auth:api']);
$middleware = is_array($middleware)
    ? $middleware
    : array_values(array_filter(array_map('trim', explode(',', (string)$middleware))));

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::get('/', [PermissionsController::class, 'get_authenticated_permissions'])
            ->name('rest-generic-class.permissions.authenticated');
        Route::get('/by-roles', [PermissionsController::class, 'get_permissions_by_roles'])
            ->name('rest-generic-class.permissions.by-roles');
        Route::get('/by-users', [PermissionsController::class, 'get_permissions_by_users'])
            ->name('rest-generic-class.permissions.by-users');
    });
