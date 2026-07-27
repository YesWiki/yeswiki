<?php

namespace YesWiki\Core\Entity;

class PackageTool extends PackageExt
{
    public const TOOL_PATH = '/extensions/';
    public const CUSTOM_TOOL_PATH = '/custom/extensions/';

    protected function localPath()
    {
        // ticket 25: the same structural main-vs-satellite test as
        // AutoUpdateService::isDesignatedUpdateInstance() (ADR-0007). The main
        // install (or a standalone wiki) installs extensions "wide" into the
        // shared source-scoped extensions/; a farm satellite instance can only
        // install into its own custom/extensions/ and never writes into the
        // shared directory.
        if (realpath(YESWIKI_INSTANCE_DIR) === realpath(YESWIKI_SOURCE_DIR)) {
            return YESWIKI_SOURCE_DIR . $this::TOOL_PATH . $this->name . '/';
        }

        return YESWIKI_INSTANCE_DIR . $this::CUSTOM_TOOL_PATH . $this->name . '/';
    }
}
