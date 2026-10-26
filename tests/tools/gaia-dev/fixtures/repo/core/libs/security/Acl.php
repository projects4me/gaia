<?php

// Test fixture for tests/tools/gaia-dev: the parts of Acl the extractor reads.
namespace Gaia\Libraries\Security;

class Acl
{
    public const RESOLUTION_PERMISSIVE = 'permissive';
    public const RESOLUTION_RESTRICTIVE = 'restrictive';
    public const SCOPE_NONE = 0;
    public const SCOPE_ALL = 1;
    public const SCOPE_MEMBERS = 2;

    protected $di;

    public function __construct($di)
    {
    }

    public function isScopedResource($resourceName)
    {
        $scoped = ['widget.get'];

        return in_array($resourceName, $scoped, true);
    }

    protected function loadPermissions($userId)
    {
    }

    public static function normalizeResolutionMode($mode)
    {
    }
}
