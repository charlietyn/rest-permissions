<?php

/*
 * Backward compatibility for code written against ronu/rest-generic-class 2.x, where the route
 * permissions lived under Ronu\RestGenericClass\Core\…. Old names are aliased to their new class
 * lazily, the first time they are autoloaded (nothing is loaded at bootstrap), and each one reports
 * a deprecation once. The aliases are kept for the whole 1.x line of ronu/rest-permissions.
 */

spl_autoload_register(static function (string $class): void {
    static $map = null;
    $map ??= require __DIR__ . '/legacy-aliases-map.php';
    if (!isset($map[$class])) {
        return;
    }
    @trigger_error(
        sprintf('%s is deprecated since ronu/rest-permissions 1.0; use %s.', $class, $map[$class]),
        E_USER_DEPRECATED
    );
    class_alias($map[$class], $class);
});
