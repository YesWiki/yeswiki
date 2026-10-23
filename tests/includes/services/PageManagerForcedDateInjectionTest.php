<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The forced-date argument of save() lands in an INSERT as a quoted literal, so only the exact datetime every caller produces is allowed through; anything else is ignored. */
class PageManagerForcedDateInjectionTest extends YesWikiTestCase
{
    private const HOSTILE_TAG = 'ForcedDateHostilePage';
    private const VALID_TAG = 'ForcedDateValidPage';

    public function testAHostileForcedDateIsIgnoredAndTheRowIsStillSavedWithANormalTime()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $hostile = '2020-01-01 00:00:00", body = \'INJECTED\', owner = \'INJECTED\' -- -';

        try {
            $pageManager->save(self::HOSTILE_TAG, 'legitimate body', '', true, $hostile);

            $page = $pageManager->getOne(self::HOSTILE_TAG, null, false, true);
            $this->assertIsArray($page, 'the row must still be saved');
            $this->assertSame('legitimate body', $page['body'], 'the injected body must not have taken effect');
            $this->assertStringNotContainsString('INJECTED', (string)$page['time'], 'the hostile value must not reach the time column');
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$page['time'], 'the ignored date falls back to now()');
        } finally {
            $pageManager->deleteOrphaned(self::HOSTILE_TAG);
        }
    }

    public function testAValidForcedDateIsHonoured()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::VALID_TAG, 'body', '', true, '2019-03-04 05:06:07');

            $page = $pageManager->getOne(self::VALID_TAG, null, false, true);
            $this->assertSame('2019-03-04 05:06:07', (string)$page['time']);
        } finally {
            $pageManager->deleteOrphaned(self::VALID_TAG);
        }
    }
}
