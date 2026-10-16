<?php

namespace Ronu\RestPermissions\Tests\Unit;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Code written against rest-generic-class 2.x keeps working: every moved class, interface and trait
 * is reachable under its old Ronu\RestGenericClass\Core\… name, lazily and with a deprecation.
 */
final class LegacyAliasesTest extends TestCase
{
    private const NEW_ONLY = ['RestPermissionsServiceProvider', 'LegacyConfig'];

    public function testTheMapCoversEveryMovedTypeAndNothingElse(): void
    {
        $map = require dirname(__DIR__, 2) . '/src/legacy-aliases-map.php';
        $expected = [];
        $src = dirname(__DIR__, 2) . '/src/';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $src)), -4);
            if ($file->getExtension() !== 'php' || str_starts_with($relative, 'legacy-aliases') || in_array($relative, self::NEW_ONLY, true)) {
                continue;
            }
            $name = str_replace('/', '\\', $relative);
            $expected['Ronu\\RestGenericClass\\Core\\' . $name] = 'Ronu\\RestPermissions\\' . $name;
        }
        ksort($expected);
        ksort($map);
        $this->assertSame($expected, $map, 'regenerate src/legacy-aliases-map.php');
        foreach ($map as $new) {
            $this->assertTrue(
                class_exists($new) || interface_exists($new) || trait_exists($new),
                "{$new} must be autoloadable on its own (one type per file)"
            );
        }
    }

    #[RunInSeparateProcess]
    public function testOldNamesResolveLazilyToTheNewTypesWithADeprecation(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            if ($level === E_USER_DEPRECATED) {
                $deprecations[] = $message;
            }
            return true;
        });
        try {
            // Nothing is loaded at bootstrap.
            $this->assertFalse(class_exists(\Ronu\RestPermissions\Support\Permissions\ActionPattern::class, false));

            $old = 'Ronu\\RestGenericClass\\Core\\Support\\Permissions\\ActionPattern';
            $this->assertTrue(class_exists($old));
            $this->assertSame(\Ronu\RestPermissions\Support\Permissions\ActionPattern::class, (new \ReflectionClass($old))->getName());
            $this->assertTrue($old::isPattern('admin.crm.*'));
            class_exists($old); // second use: no second deprecation

            $this->assertTrue(interface_exists('Ronu\\RestGenericClass\\Core\\Support\\Permissions\\Contracts\\ProvidesRoles'));
            // Middleware registered by its old name in bootstrap/app.php or Kernel.php.
            $this->assertSame(
                \Ronu\RestPermissions\Middleware\SpatieAuthorize::class,
                (new \ReflectionClass('Ronu\\RestGenericClass\\Core\\Middleware\\SpatieAuthorize'))->getName()
            );
        } finally {
            restore_error_handler();
        }

        $this->assertCount(3, $deprecations);
        $this->assertStringContainsString(
            'Ronu\\RestGenericClass\\Core\\Support\\Permissions\\ActionPattern is deprecated since ronu/rest-permissions 1.0; use Ronu\\RestPermissions\\Support\\Permissions\\ActionPattern.',
            $deprecations[0]
        );
    }

    #[RunInSeparateProcess]
    public function testAnApplicationClassCanUseAnOldTraitName(): void
    {
        set_error_handler(static fn (): bool => true, E_USER_DEPRECATED);
        try {
            eval(<<<'PHP'
                namespace App\Models;

                class LegacyUser extends \Illuminate\Foundation\Auth\User
                    implements \Ronu\RestGenericClass\Core\Support\Permissions\Contracts\ProvidesRoles
                {
                    use \Ronu\RestGenericClass\Core\Traits\HasReadableUserPermissions;
                }
                PHP);
        } finally {
            restore_error_handler();
        }
        $traits = class_uses(\App\Models\LegacyUser::class);
        $this->assertContains(\Ronu\RestPermissions\Traits\HasReadableUserPermissions::class, $traits);
        $this->assertInstanceOf(\Ronu\RestPermissions\Support\Permissions\Contracts\ProvidesRoles::class, new \App\Models\LegacyUser());
    }
}
