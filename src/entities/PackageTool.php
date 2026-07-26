<?php

namespace YesWiki\Core\Entity;

class PackageTool extends PackageExt
{
    public const TOOL_PATH = '/tools/';

    protected function localPath()
    {
        return
            YESWIKI_SOURCE_DIR
            . $this::TOOL_PATH
            . $this->name
            . '/';
    }
}
