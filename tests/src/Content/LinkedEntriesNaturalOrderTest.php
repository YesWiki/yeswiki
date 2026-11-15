<?php

namespace YesWiki\Test\Content;

use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Content\Field\EnumField;

class LinkedEntriesNaturalOrderTest extends YesWikiTestCase
{
    public function testNumbersCaseAndAccentsSortTheWayAReaderExpects(): void
    {
        $options = ['a' => 'Zoé', 'b' => 'Atelier 10', 'c' => 'Élodie', 'd' => 'atelier 2'];

        uasort($options, [EnumField::class, 'naturalCompare']);

        $this->assertSame(['atelier 2', 'Atelier 10', 'Élodie', 'Zoé'], array_values($options));
        $this->assertSame(['d', 'b', 'c', 'a'], array_keys($options));
    }
}
