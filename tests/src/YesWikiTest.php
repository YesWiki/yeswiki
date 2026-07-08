<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(Wiki::class, '__construct')]
class YesWikiTest extends YesWikiTestCase
{
    public function testInitWiki(): Wiki
    {
        $wiki = $this->getWiki();
        // services should not be empty
        $this->assertTrue(!is_null($wiki));
        $this->assertTrue($wiki->services->has(Wiki::class));

        return $wiki;
    }
}
