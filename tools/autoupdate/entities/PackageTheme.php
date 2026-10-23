<?php

namespace YesWiki\AutoUpdate\Entity;

class PackageTheme extends PackageExt
{
    public const THEME_PATH = '/themes/';

    protected function localPath()
    {
        return
            $this->wikiRootPath()
            . $this::THEME_PATH
            . $this->name
            . '/';
    }
}
