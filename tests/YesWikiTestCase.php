<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\TestCase;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Wiki;

class YesWikiTestCase extends TestCase
{
    protected function getWiki(): Wiki
    {
        require_once 'includes/YesWikiLoader.php';
        $wiki = YesWikiLoader::getWiki(true);

        return $wiki;
    }
}
