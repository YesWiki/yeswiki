<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** No page is saved under an empty tag: deleting it would take every page that is not a comment. */
class PageManagerEmptyTagTest extends YesWikiTestCase
{
    public function testSavingUnderAnEmptyTagIsRefused()
    {
        $services = $this->getWiki()->services;
        $dbService = $services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');

        foreach (['', ' '] as $tag) {
            try {
                $services->get(PageManager::class)->save($tag, '', '', true);
                $this->fail('saving under an empty tag is refused');
            } catch (\InvalidArgumentException $refused) {
                $this->assertSame(_t('INCORRECT_PAGENAME'), $refused->getMessage());
            }
        }
        $this->assertNull($dbService->loadSingle("SELECT id FROM {$pages} WHERE TRIM(tag) = ''"));
    }
}
