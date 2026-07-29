<?php

namespace YesWiki\Kernel\Service;

/**
 * The installed extensions, `name => absolute base path` (historic
 * Wiki::$extensions). Discovered pre-boot (the list drives the container
 * build itself) and bound here by reference once the container exists.
 */
class ExtensionRegistry
{
    /** @var array<string, string> */
    protected $extensions = [];

    /** @param array<string, string> $extensions */
    public function bind(array &$extensions): void
    {
        $this->extensions = &$extensions;
    }

    /** @return array<string, string> name => base path */
    public function all(): array
    {
        return $this->extensions;
    }
}
