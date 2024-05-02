<?php

namespace YesWiki\AutoUpdate\Entity;

class PackageTheme extends PackageExt
{
    public const THEME_PATH = '/themes/';

    protected function localPath()
    {
        return
            dirname(dirname(dirname(__DIR__)))
            . $this::THEME_PATH
            . $this->name
            . '/';
    }
}
