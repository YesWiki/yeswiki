<?php
namespace AutoUpdate;

class PackageTheme extends PackageExt
{
    const THEME_PATH = '/themes/';

    protected function localPath()
    {
        return
            dirname(dirname(dirname(__DIR__)))
            . $this::THEME_PATH
            . $this->name
            . '/';
    }
}
