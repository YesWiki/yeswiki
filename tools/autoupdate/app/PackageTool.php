<?php
namespace AutoUpdate;

class PackageTool extends PackageExt
{
    const TOOL_PATH = '/tools/';

    protected function localPath()
    {
        return
            dirname(dirname(dirname(__DIR__)))
            . $this::TOOL_PATH
            . $this->name
            . '/';
    }
}
