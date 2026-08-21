<?php

namespace YesWiki\Admin\Entity;

class PackageTool extends PackageExt
{
    public const TOOL_PATH = '/extensions/';
    public const CUSTOM_TOOL_PATH = '/custom/extensions/';

    protected function localPath()
    {
        if (realpath(YESWIKI_INSTANCE_DIR) === realpath(YESWIKI_PROGRAM_DIR)) {
            return YESWIKI_PROGRAM_DIR . $this::TOOL_PATH . $this->name . '/';
        }

        return YESWIKI_INSTANCE_DIR . $this::CUSTOM_TOOL_PATH . $this->name . '/';
    }
}
