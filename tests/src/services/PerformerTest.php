<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

#[CoversMethod(Performer::class, '__construct')]
#[CoversMethod(Performer::class, 'list')]
class PerformerTest extends YesWikiTestCase
{
    public function testPerformerExisting(): Performer
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(Performer::class));

        return $wiki->services->get(Performer::class);
    }

    #[Depends('testPerformerExisting')]
    #[DataProvider('listProvider')]
    public function testList(string $objectType, Performer $performer)
    {
        $list = $performer->list($objectType);
        $this->assertTrue(is_array($list));
        $this->assertGreaterThan(0, count($list));
        foreach ($list as $elem) {
            $this->assertIsString($elem);
        }
    }

    public static function listProvider()
    {
        // objectType
        return [
            'actions' => ['action'],
            'handlers' => ['handler'],
            'formatters' => ['formatter'],
        ];
    }
}
