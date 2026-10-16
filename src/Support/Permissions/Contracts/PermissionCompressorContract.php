<?php

namespace Ronu\RestPermissions\Support\Permissions\Contracts;

use Illuminate\Support\Collection;
use Ronu\RestPermissions\Support\Permissions\PermissionCompressedResult;

interface PermissionCompressorContract
{
    public function compress(
        Collection $permissions,
        Collection $allSystemPerms,
        array $options = []
    ): PermissionCompressedResult;
}
