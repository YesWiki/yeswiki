<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Api\TripleApiController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `POST /api/triples` reads its parameters from the request **body**.
 *
 * It did not. `extractTriplesParams()` took `INPUT_GET` / `INPUT_POST` — which are ints — in a
 * parameter declared `string`. PHP coerced the constant, so the `$method === INPUT_POST` inside
 * compared `"0"` against `0` with `===` and was **always false**: every call, POST included,
 * read `property` and `user` off the query string.
 *
 * One `argument.type` entry in the PHPStan baseline had been saying so (ticket 40). Nothing
 * failed, because a POST that supplies its parameters in the URL still works — which is exactly
 * how the API is exercised by hand.
 */
class TripleApiPostParamsTest extends YesWikiTestCase
{
    public function testTheMethodParameterKeepsTheTypeItsConstantsHave(): void
    {
        // boots the wiki so the controller's base class is autoloadable before reflecting
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

    /**
     * The bag the method picks, which is the behaviour the wrong type broke.
     */
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
