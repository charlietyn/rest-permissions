# Configuración

Se publica con `php artisan vendor:publish --tag=rest-permissions-config` en `config/rest-permissions.php`. Hasta rest-generic-class 2.x estas claves vivían en `config/rest-generic-class.php` bajo `permissions`; ese bloque se sigue respetando (con un aviso de obsolescencia) mientras no se publique `config/rest-permissions.php`. La autorización de cargas anidadas (`?relations=`) es configuración del núcleo: ver los [contratos de autorización](https://github.com/charlietyn/rest-generic-class/blob/main/documentacion/doc-es/04-reference/06-authorization-contracts.md) del núcleo.

## Rutas opcionales de permisos

El paquete puede registrar rutas reutilizables de lectura de permisos. Estan deshabilitadas por defecto para que las aplicaciones existentes mantengan control total de su tabla de rutas.

| Clave | Tipo | Default | Descripcion |
| --- | --- | --- | --- |
| `roles_relation` | string | `roles` | Nombre de la relacion Eloquent del modelo User que devuelve sus roles. Fallback global; cada modelo puede sobreescribirlo con `const ROLES_RELATION`. Soporta cardinalidad uno-a-muchos (un rol) y muchos-a-muchos (varios roles). |
| `routes.enabled` | bool | `false` | Cuando es true, carga las rutas de permisos del paquete. |
| `routes.prefix` | string | `permissions` | Prefijo de rutas. Agrega prefijos de app como `/api` en tu grupo de rutas o kernel HTTP, no aqui. |
| `routes.middleware` | array | `['api', 'auth:api']` | Middleware aplicado a las rutas del paquete. |
| `routes.guard` | string | `api` | Guard usado para resolver el usuario autenticado en la ruta opcional del paquete. No filtra el payload salvo que la solicitud tambien envie `guard`. |

Rutas registradas cuando se habilita:

```http
GET /permissions
GET /permissions/by-roles
GET /permissions/by-users
```

Si la aplicacion consumidora tambien define `apiResource('permissions')`, registra las rutas especificas anteriores antes del resource route.

## Contratos de permisos (User y Role)

El paquete obliga al integrador a declarar **contratos formales** en sus modelos User y Role (ver [guia detallada](permissions.md)). Esta seccion del config es **opcional** y solo activa la validacion temprana en arranque; los contratos siempre se aplican en runtime aunque las claves esten vacias.

| Clave | Tipo | Default | Descripcion |
| --- | --- | --- | --- |
| `contracts.user_model` | string\|null | `null` | FQCN del modelo User. Si se declara, el `ServiceProvider` valida en `boot()` que la clase implementa `Ronu\RestPermissions\Support\Permissions\Contracts\ProvidesRoles`. |
| `contracts.role_model` | string\|null | `null` | FQCN del modelo Role. Si se declara, el `ServiceProvider` valida en `boot()` que la clase implementa `Ronu\RestPermissions\Support\Permissions\Contracts\ProvidesRolePermissions`. |

Si una de estas clases no implementa la interface esperada, la app falla en arranque con `RolesContractViolationException` (mensaje accionable que indica exactamente que clase debe implementar que interface).

```php
// Ejemplo
'permissions' => [
    'contracts' => [
        'user_model' => \App\Models\User::class,
        'role_model' => \App\Models\Role::class,
    ],
],
```

## Autorización por canales (v2.6.0)

`eager_relations` / `eager_relations_to_one` pasaron al núcleo (`rest-generic-class.authorization.*`).

Todas las claves siguientes pertenecen a `authorization`.

| Clave | Predeterminado | Significado |
| --- | --- | --- |
| `route_prefixes` | `null` | Legacy: el prefijo del grupo Laravel empieza por el nombre del guard. Un array compara prefijos de URI por segmentos (`admin` nunca coincide con `administration`); `[]` no selecciona rutas. Admite prefijos anidados como `api/admin`; gana la primera coincidencia. |
| `naming` | `derived` | Infiere `{version}.{module}.{model}.{action}` (versión/módulo opcionales). `route_name` usa el nombre literal de la ruta, incluidos `store`/`destroy`, sin traducción ni versión adicional; una ruta sin nombre no se resuelve. |
| `on_unresolved` | `allow` | `allow` continúa sin comprobar permisos. `deny` devuelve 403 en cualquier ruta no resuelta que tenga el middleware, incluidas las rutas fuera de los prefijos configurados. |
| `register_gate` | `false` | Registra un `Gate::before` opcional para usuarios con `effectivePermissions()` sin `checkPermissionTo()` de Spatie. También incorpora permisos globales a la unión efectiva del trait. |
| `gate_guard` | `null` | Filtra los permisos efectivos del Gate por guard; `null` admite todos. Usa `api` si ese es tu guard de autenticación. |
| `explicit_globals` | `false` | Fase 2: `false` conserva los globales por módulo (una ruta sin módulo reconocido es global). `true` guarda `restrict=false` solo en las rutas cuya **acción** lleva `'permission_global' => true` (incluso dentro de un módulo); el resto de permisos sincronizados queda restringido (falla cerrando). |
| `catalog_middleware` | `null` | Fase 2, solo catálogo: `null` incluye todas las rutas del ámbito. Un array incluye solo las rutas cuyo `gatherMiddleware()` contiene un string que empieza por uno de los marcadores (alias o FQCN); `[]` no incluye ninguna. Ignora middleware que no sean string. |

`route_prefixes` selecciona rutas; no define guards de autenticación. El parámetro del middleware y `--guard` del comando deben seguir usando guards reales. Ya no se lee la antigua clave `route-permissions`. `passesFilters()` permanece como punto de extensión sobrescribible, sin reglas de filtrado incorporadas.

El valor `allow` se conserva por compatibilidad, pero queda obsoleto y es inseguro en rutas protegidas; se prevé cambiar el valor por defecto a `deny` en el próximo major de ronu/rest-permissions (en 1.x sigue en `allow` para que actualizar no cambie el comportamiento).

La corrección legacy de fase 2 recupera la coincidencia exacta de módulos de v2.5.0 cuando `route_prefixes=null`: la URI debe tener más de dos segmentos no vacíos, empezar por el segmento literal del guard y usar el nombre exacto del módulo. `api/mod_security/users` con módulo `ModSecurity` produce `users.index`; `api/ModSecurity/users` produce `ModSecurity.users.index`. La coincidencia snake_case solo se aplica con prefijos de canales explícitos. Se conservan las correcciones de versión y rutas sin controlador introducidas en v2.6.0.

`explicit_globals` controla lo que escribe la sincronización, no el lector ni el Gate. Declara `permission_global` como atributo de un grupo de rutas o con `setAction()`; nunca con `->defaults()`, que Laravel convierte en un parámetro de ruta que llega a los controladores (y el resolver lo ignora). Resincronizar actualiza `restrict` en las filas existentes seleccionadas. El Gate opcional sigue concediendo los permisos persistidos `notRestricted()` a cualquier usuario autenticado de su guard. `catalog_middleware` no cambia la autorización de peticiones, no expande alias/grupos y no elimina filas: borra explícitamente los permisos obsoletos tras revisar sus asignaciones. Las filas excluidas no se actualizan, incluido su estado global anterior.

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
// routes/admin.php: atributo de grupo, copiado a la acción de cada ruta
Route::group(['as' => 'admin.', 'permission_global' => true], function () {
    Route::get('auth/permissions', [AuthController::class, 'permissions'])->name('auth.permissions');
    Route::get('auth/user-profile', [AuthController::class, 'profile'])->name('auth.user-profile');
});

// una ruta suelta fuera de un grupo
$route = Route::get('auth/modules', [AuthController::class, 'modules'])->name('admin.auth.modules');
$route->setAction([...$route->getAction(), 'permission_global' => true]);
```

## Variables de entorno

| Variable | Default | Usado para |
| --- | --- | --- |
| `REST_PERMISSIONS_ROLES_RELATION` | `roles` | Nombre por defecto de la relacion del modelo User que devuelve sus roles. Cada modelo puede sobreescribirlo con `const ROLES_RELATION`. |
| `REST_PERMISSIONS_ROUTES_ENABLED` | `false` | Habilita las rutas de lectura de permisos provistas por el paquete. |
| `REST_PERMISSIONS_ROUTES_PREFIX` | `permissions` | Prefijo para las rutas opcionales de permisos. |
| `REST_PERMISSIONS_ROUTES_MIDDLEWARE` | `api,auth:api` | Lista de middleware separada por comas para las rutas opcionales. |
| `REST_PERMISSIONS_ROUTES_GUARD` | `api` | Guard usado para resolver el usuario autenticado en rutas opcionales de permisos. |
| `REST_PERMISSIONS_USER_MODEL` | _(no definida)_ | FQCN del modelo User para validacion temprana del contrato `ProvidesRoles` en boot. Opcional; si esta vacia la validacion ocurre en runtime. |
| `REST_PERMISSIONS_ROLE_MODEL` | _(no definida)_ | FQCN del modelo Role para validacion temprana del contrato `ProvidesRolePermissions` en boot. Opcional; si esta vacia la validacion ocurre en runtime. |
| `REST_AUTHZ_ROUTE_PREFIXES` | sin definir (`null`) | Prefijos separados por comas; elimina espacios. Un valor vacío produce `[]`. |
| `REST_AUTHZ_NAMING` | `derived` | `derived` o `route_name`. |
| `REST_AUTHZ_ON_UNRESOLVED` | `allow` | `allow` o `deny`; usa `deny` en rutas protegidas. |
| `REST_AUTHZ_REGISTER_GATE` | `false` | Activa el Gate de permisos efectivos y la unión con globales. |
| `REST_AUTHZ_GATE_GUARD` | sin definir (`null`) | Guard opcional, por ejemplo `api`. |
| `REST_AUTHZ_EXPLICIT_GLOBALS` | `false` | Fase 2: `true` hace globales solo las rutas marcadas con el atributo de acción `permission_global`; `false` conserva los globales de rutas sin módulo. |
| `REST_AUTHZ_CATALOG_MIDDLEWARE` | sin definir (`null`) | Fase 2: marcadores de middleware, alias o FQCN, separados por comas. Sin definir incluye todas las rutas del ámbito; vacío produce `[]` (ninguna ruta en el catálogo). |

Las listas de prefijos y middleware recortan espacios, descartan entradas vacías y conservan valores como `0`. `null` y una lista vacía tienen significados distintos. Ejemplo:

```dotenv
REST_AUTHZ_EXPLICIT_GLOBALS=true
REST_AUTHZ_CATALOG_MIDDLEWARE=maybe.permission
```

Actualiza la caché de configuración y después sincroniza permisos para aplicar estas opciones. Cambiar el entorno por sí solo no actualiza restricciones persistidas ni elimina permisos obsoletos.


[Volver al README](../../README.md)
