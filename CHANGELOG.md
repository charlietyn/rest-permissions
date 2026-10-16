# Changelog

## 1.0.0 — Extracted from rest-generic-class / Extraído de rest-generic-class

Status / Estado: **ready / lista** (2026-10-16), not yet published / sin publicar. Released together with
rest-generic-class 3.0.0. / Se publica junto con rest-generic-class 3.0.0.

### English

- The route permissions of rest-generic-class 2.9 (catalog sync, `SpatieAuthorize`, `RouteMetaResolver`,
  effective-permission Gate, readers, compressor, role assignment, Spatie models, `PermissionsController`, the
  permission routes and the channel-authorization migration script) move here with their git history, under
  `Ronu\RestPermissions\…`. Same behaviour as rest-generic-class 2.9.0.
- `RestPermissionsServiceProvider` (auto-discovered) binds `RouteAuthorizationContext` as the core's
  `AuthorizationContext` (any provider order), and registers the Gate (`register_gate`), the routes
  (`routes.enabled`) and the contract checks.
- Configuration in `config/rest-permissions.php` (`authorization`, `roles_relation`, `routes`, `contracts`); same
  `REST_AUTHZ_*` / `REST_PERMISSIONS_*` variables. Eager-load authorization stays in the core.
- **Backward compatibility for the whole 1.x line:** lazy `class_alias` of every 2.x name with a one-time
  `E_USER_DEPRECATED` (works for classes, interfaces and traits); the `permissions` block of
  `config/rest-generic-class.php` is applied while `config/rest-permissions.php` is not published (lists are
  replaced, not merged by index); route names keep `rest-generic-class.permissions.*`.
- `RouteMeta` now has its own file (it was declared inside `RouteMetaResolver.php` and could not be autoloaded alone).
- The migration script checks `ronu/rest-permissions` ≥ 1.0 and accepts the middleware under either name.

### Español

- Los permisos por rutas de rest-generic-class 2.9 (sincronización del catálogo, `SpatieAuthorize`,
  `RouteMetaResolver`, Gate de permisos efectivos, lectores, compresor, asignación a roles, modelos Spatie,
  `PermissionsController`, rutas de permisos y el script de migración) se mueven aquí con su historial de git,
  bajo `Ronu\RestPermissions\…`. Mismo comportamiento que rest-generic-class 2.9.0.
- `RestPermissionsServiceProvider` (autodescubierto) registra `RouteAuthorizationContext` como `AuthorizationContext`
  del núcleo (en cualquier orden de providers), el Gate, las rutas y la validación de contratos.
- Configuración en `config/rest-permissions.php`; mismas variables de entorno. La autorización de `?relations=`
  sigue en el núcleo.
- **Compatibilidad durante toda la línea 1.x:** alias perezosos de cada nombre 2.x con un aviso de obsolescencia
  único (clases, interfaces y traits); el bloque `permissions` de `config/rest-generic-class.php` se aplica mientras
  no se publique `config/rest-permissions.php`; los nombres de ruta se conservan.
- `RouteMeta` tiene su propio archivo.

### Verification / Verificación (2026-10-16, local)

One revision of each package / una revisión de cada paquete. `composer validate --strict` and `composer audit` clean in every row.

| Package | PHP | Laravel | Spatie | Result |
| --- | --- | --- | --- | --- |
| rest-generic-class 3.0 | 8.2.30 / 8.3.28 / 8.3.28 / 8.4.15 / 8.5.0 | 12.69.0 / 12.70.0 / 13.30.1 / 13.37.0 / 13.37.0 | not installed / no instalado | 192 tests, 500 assertions, OK (×5) |
| rest-permissions 1.0 | 8.2.30 | 12.69.0 | 6.21.0, 6.25.0 | 172 tests, 598 assertions, OK |
| rest-permissions 1.0 | 8.3.28 / 8.4.15 | 12.70.0 | 7.4.2, 7.0.0, 8.3.0 | 172 tests, OK |
| rest-permissions 1.0 | 8.3.28 / 8.4.15 / 8.5.0 | 13.30.1 / 13.37.0 | 6.25.0, 7.4.2, 8.0.0, 8.3.0 | 172 tests, OK |

The plugin's `tests/Integration` suite also passes on MySQL 8.4 (19 tests). / La suite de integración del plugin
también pasa en MySQL 8.4.

Generated `eros` apps, isolated copies, both packages from local `path` repositories, **app code unchanged**
(it still uses the 2.x names: `bootstrap/app.php` registers `…\Core\Middleware\SpatieAuthorize`, models extend
`…\Core\Models\SpatiePermission|SpatieRole`, console commands use `…\Core\Traits\HasPermissionsService`):

| App | `package:discover` | `route:list` | Protected route through the app's `auto.authorize` (2.x alias) |
| --- | --- | --- | --- |
| Laravel 12.70.0 + Spatie 6.25.0 | OK (rest-generic-class, rest-permissions, spatie) | 3428 routes | 403 without the permission, 200 with it |
| Laravel 13.36.0 + Spatie 8.3.0 (existing lock, targeted update) | OK | 3428 routes | 403 / 200 |

In both, the old middleware name resolves to `Ronu\RestPermissions\Middleware\SpatieAuthorize`, the core's
`AuthorizationContext` is the plugin's `RouteAuthorizationContext` and `require_read` (set in the app's `.env`)
works. Only generated `bootstrap/cache/*.php` files differ from the original app.
