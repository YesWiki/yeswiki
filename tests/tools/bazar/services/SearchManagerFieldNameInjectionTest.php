<?php

namespace YesWiki\Test\Bazar\Service;

use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Field names reach the search SQL as identifiers, so anything that is not one must never get there.
 */
class SearchManagerFieldNameInjectionTest extends YesWikiTestCase
{
    private const PAYLOAD = 'x`,(SELECT SLEEP(5)) as `d';

    private function request(array $params): string
    {
        $wiki = $this->getWiki();
        $forms = $wiki->services->get(FormManager::class)->getAll();
        if (empty($forms)) {
            $this->markTestSkipped('needs a form');
        }

        $params += ['formsIds' => [array_key_first($forms)]];

        return $wiki->services->get(SearchManager::class)->prepareSearchRequest($params);
    }

    public function testAQueryOnAnInjectedFieldNameMatchesNothing()
    {
        $this->assertSame('', $this->request(['queries' => self::PAYLOAD . '!=x']));
        $this->assertSame('', $this->request(['queries' => 'bf_titre!=x|' . self::PAYLOAD . '==x']));
    }

    public function testAnInjectedSearchFieldIsIgnored()
    {
        $sql = $this->request(['keywords' => 'robot', 'searchfields' => 'bf_titre,' . self::PAYLOAD]);

        $this->assertNotSame('', $sql);
        $this->assertStringNotContainsString('SLEEP', $sql);
    }

    public function testRealFieldNamesStillReachTheQuery()
    {
        $this->assertStringContainsString('bf_titre', $this->request(['queries' => 'bf_titre!=x']));
        $this->assertStringContainsString('geolocation__bf_latitude', $this->request(['queries' => 'geolocation.bf_latitude!=x']));
    }
}
