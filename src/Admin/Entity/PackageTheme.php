<?php

namespace YesWiki\Admin\Entity;

class PackageTheme extends PackageExt
{
    public const THEME_PATH = '/themes/';
    public const CUSTOM_THEME_PATH = '/custom/themes/';

    /** Mirrors PackageTool: the Program tree when there is only one wiki, the Instance's own `custom/themes/` when the two differ. */
    protected function localPath()
    {
        if (self::installsIntoInstance()) {
            return YESWIKI_INSTANCE_DIR . $this::CUSTOM_THEME_PATH . $this->name . '/';
        }

        return YESWIKI_PROGRAM_DIR . $this::THEME_PATH . $this->name . '/';
    }
}
