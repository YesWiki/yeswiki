<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class PerformerTest extends YesWikiTestCase
{
    /**
     * @covers \Performer::__construct
     *
     * @return Performer $performer
     */
    public function testPerformerExisting(): Performer
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(Performer::class));

        return $wiki->services->get(Performer::class);
    }

    /**
     * @depends testPerformerExisting
     * @dataProvider listProvider
     * @covers \Performer::list
     */
    public function testList(string $objectType, Performer $performer)
    {
        $list = $performer->list($objectType);
        $this->assertTrue(is_array($list));
        $this->assertGreaterThan(0, count($list));
        foreach ($list as $elem) {
            $this->assertIsString($elem);
        }
    }

    public function listProvider()
    {
        // objectType
        return [
            'actions' => ['action'],
            'handlers' => ['handler'],
            'formatters' => ['formatter'],
        ];
    }
}
