<?php

namespace YesWiki\AutoUpdate\Entity;

class PackageTool extends PackageExt
{
    public const TOOL_PATH = '/tools/';

    protected function localPath()
    {
        return
            $this->wikiRootPath()
            . $this::TOOL_PATH
            . $this->name
            . '/';
    }
}
