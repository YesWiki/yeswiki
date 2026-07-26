<?php

namespace YesWiki\Core\Entity;

class PackageTheme extends PackageExt
{
    public const THEME_PATH = '/themes/';

    protected function localPath()
    {
        return
            YESWIKI_SOURCE_DIR
            . $this::THEME_PATH
            . $this->name
            . '/';
    }
}
