<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\TestCase;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Wiki;

class YesWikiTest extends TestCase
{
    /**
     * @covers Wiki::__construct
     */
    public function testInitWiki():Wiki
    {
        require_once 'includes/YesWikiLoader.php';
        $wiki = YesWikiLoader::getWiki();
        // services should not be empty
        $this->assertTrue(!is_null($wiki));
        $this->assertTrue($wiki->services->has(Wiki::class));

        return $wiki;
    }
}
