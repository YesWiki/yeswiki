<?php

namespace YesWiki\Admin\Entity;

class PackageTheme extends PackageExt
{
    public const THEME_PATH = '/themes/';

    protected function localPath()
    {
        return
            YESWIKI_PROGRAM_DIR
            . $this::THEME_PATH
            . $this->name
            . '/';
    }
}
