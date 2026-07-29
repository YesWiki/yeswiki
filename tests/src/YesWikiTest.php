<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(YesWikiRuntime::class, '__construct')]
class YesWikiTest extends YesWikiTestCase
{
    public function testInitWiki(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        // the container must know its own runtime
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki;
    }
}
