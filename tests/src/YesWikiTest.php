<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Core\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(YesWikiRuntime::class, '__construct')]
class YesWikiTest extends YesWikiTestCase
{
    public function testInitWiki(): YesWikiRuntime
    {
        $wiki = $this->getWiki();

        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki;
    }
}
