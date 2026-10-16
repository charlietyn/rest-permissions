<?php
declare(strict_types=1);

namespace Ronu\RestPermissions\Resolvers;

/** What RouteMetaResolver resolved for one route: its canonical action, alternatives and requirements. */
final class RouteMeta
{
    /** @var string[] Absolute alternative actions; holding any one of them is enough (canAny). */
    public readonly array $abilities;

    /** @var string[] Absolute additional actions; all of them are required (canAll). */
    public readonly array $requires;

    /**
     * @param string[] $abilities Defaults to [$canonicalName].
     * @param string[] $requires
     */
    public function __construct(
        public readonly ?string $routeName,     // e.g. {{security_module}}.role.index
        public readonly string  $uri,           // e.g. api/{{security_module}}/roles/{id}
        public readonly array   $verbs,         // e.g. ['GET','HEAD']
        public readonly ?string $controller,    // e.g. Modules\...\RolesController
        public readonly ?string $method,        // e.g. index|store|update|destroy
        public readonly string  $module,        // e.g. {{security_module}} | --site--
        public readonly string  $model,         // e.g. role
        public readonly string  $action,        // e.g. view|create|update|delete (o index/show si prefieres)
        public readonly string  $canonicalName, // e.g. {{security_module}}.role.view; the first ability
        public readonly string  $controllerAction, // e.g. RolesController@index
        public readonly bool    $global = false, // permission_global route action attribute
        array $abilities = [],
        array $requires = []
    )
    {
        $this->abilities = $abilities !== [] ? array_values($abilities) : [$canonicalName];
        $this->requires = array_values($requires);
    }
}
