<?php

namespace Ronu\RestPermissions;

use Illuminate\Contracts\Foundation\Application;

/**
 * Until rest-generic-class 2.x the permission settings lived in config/rest-generic-class.php under
 * `permissions`. When an application still has that block and has not published
 * config/rest-permissions.php, its values are applied over the package defaults (once, at register)
 * and a deprecation is reported. Remove the block after publishing rest-permissions-config.
 */
final class LegacyConfig
{
    public static function apply(Application $app): void
    {
        $config = $app['config'];
        $legacy = $config->get('rest-generic-class.permissions');
        if (!is_array($legacy) || $legacy === [] || is_file($app->configPath('rest-permissions.php'))) {
            return;
        }
        $config->set('rest-permissions', self::merge((array) $config->get('rest-permissions', []), $legacy));
        @trigger_error(
            'ronu/rest-permissions: the "permissions" block of config/rest-generic-class.php is deprecated; '
            . 'publish the rest-permissions-config tag and move the values to config/rest-permissions.php.',
            E_USER_DEPRECATED
        );
    }

    /**
     * Associative arrays merge key by key; lists and scalars from $override replace the default
     * (array_replace_recursive would merge lists such as routes.middleware index by index).
     */
    public static function merge(array $defaults, array $override): array
    {
        foreach ($override as $key => $value) {
            $defaults[$key] = is_array($value) && !array_is_list($value) && is_array($defaults[$key] ?? null) && !array_is_list($defaults[$key])
                ? self::merge($defaults[$key], $value)
                : $value;
        }
        return $defaults;
    }
}
