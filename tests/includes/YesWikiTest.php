<?php

namespace YesWiki\Test\Core;

use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

class YesWikiTest extends YesWikiTestCase
{
    /**
     * @covers \Wiki::__construct
     */
    public function testInitWiki(): Wiki
    {
        $wiki = $this->getWiki();
        // services should not be empty
        $this->assertTrue(!is_null($wiki));
        $this->assertTrue($wiki->services->has(Wiki::class));

        return $wiki;
    }
}
