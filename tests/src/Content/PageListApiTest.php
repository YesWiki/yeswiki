<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Api\PageApiController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** GET /api/pages pages through the readable pages in SQL and can leave their bodies out. */
class PageListApiTest extends YesWikiTestCase
{
    /** @return array<string, array<string, mixed>> */
    private function pages(string $query): array
    {
        parse_str($query, $params);
        $response = $this->getWiki()->services->get(PageApiController::class)->getAllPages(new Request($params));

        return json_decode((string)$response->getContent(), true) ?? [];
    }

    public function testLimitAndOffsetPageThroughTheTags(): void
    {
        $all = array_keys($this->pages('fields=tag'));
        $this->assertGreaterThan(3, count($all));

        $this->assertSame(array_slice($all, 0, 2), array_keys($this->pages('fields=tag&limit=2')));
        $this->assertSame(array_slice($all, 2, 2), array_keys($this->pages('fields=tag&limit=2&offset=2')));
    }

    public function testFieldsTagLeavesTheBodiesOut(): void
    {
        foreach ($this->pages('fields=tag&limit=5') as $tag => $page) {
            $this->assertSame(['tag' => $tag], $page);
        }
        $this->assertArrayHasKey('body', array_values($this->pages('limit=1'))[0] ?? []);
    }
}
