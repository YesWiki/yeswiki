<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Asking about no resource in particular reads every triple, with SQL each database accepts: PostgreSQL refuses a bare `WHERE 1`. */
class TripleStoreEveryResourceTest extends YesWikiTestCase
{
    public function testNoResourceReadsEveryTriple(): void
    {
        $store = self::getWiki()->services->get(TripleStore::class);

        $this->assertTrue($store->hasAnyProperty('', ''), 'a fresh wiki already holds triples');
    }
}
