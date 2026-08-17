<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Api\TripleApiController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `POST /api/triples` reads its parameters from the request **body**. */
class TripleApiPostParamsTest extends YesWikiTestCase
{
    public function testTheMethodParameterKeepsTheTypeItsConstantsHave(): void
    {
        self::getWiki();
        $method = (new \ReflectionMethod(TripleApiController::class, 'extractTriplesParams'))
            ->getParameters()[0];

        $this->assertSame(
            'int',
            (string)$method->getType(),
            'INPUT_GET and INPUT_POST are ints; declaring this `string` silently coerces them '
            . 'and breaks the identity comparison that chooses the request bag'
        );
    }

    /** The bag the method picks, which is the behaviour the wrong type broke. */
    public function testAPostReadsTheRequestBodyRatherThanTheQueryString(): void
    {
        self::getWiki();
        $source = (string)file_get_contents(dirname(__DIR__, 3) . '/src/Content/Api/TripleApiController.php');

        $this->assertStringContainsString(
            '($method === INPUT_POST) ? $this->getRequest()->request : $this->getRequest()->query',
            $source,
            'the bag is still chosen by an identity comparison against the constant'
        );
    }
}
