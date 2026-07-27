<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\EntryManager;
use YesWiki\Core\Service\FormManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 04 (tag-collision-avoidance helper, ADR-0001):
 * PageManager::suggestFreeTag() checks the global Content tag namespace and, on collision,
 * suggests a free numeric-suffixed alternative instead of failing with no path forward.
 */
class PageManagerTagCollisionTest extends YesWikiTestCase
{
    private const FREE_TAG = 'PageManagerTagCollisionRegressionFreeTag';
    private const TAKEN_TAG = 'PageManagerTagCollisionRegressionTakenTag';
    private const BAZAR_FORM_ID = '999905';
    private const BAZAR_ENTRY_TAG = 'PageManagerTagCollisionRegressionEntry';

    public function testFreeTagIsReturnedUnchanged()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $this->assertFalse($pageManager->tagExists(self::FREE_TAG));
        $this->assertSame(self::FREE_TAG, $pageManager->suggestFreeTag(self::FREE_TAG));
    }

    public function testCollidingWithAnExistingPageSuggestsAFreeAlternative()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAKEN_TAG, 'existing page content', '', true);

            $this->assertTrue($pageManager->tagExists(self::TAKEN_TAG));
            $suggested = $pageManager->suggestFreeTag(self::TAKEN_TAG);

            $this->assertNotSame(self::TAKEN_TAG, $suggested, 'requesting an already-taken tag must not return that same tag');
            $this->assertSame(self::TAKEN_TAG . '2', $suggested);
            $this->assertFalse($pageManager->tagExists($suggested), 'the suggested alternative must itself be guaranteed free');
        } finally {
            $pageManager->deleteOrphaned(self::TAKEN_TAG);
        }
    }

    public function testSuggestedAlternativeSkipsAlreadyTakenSuffixes()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAKEN_TAG, 'v1', '', true);
            $pageManager->save(self::TAKEN_TAG . '2', 'v2 -- also already taken', '', true);

            $suggested = $pageManager->suggestFreeTag(self::TAKEN_TAG);

            $this->assertSame(self::TAKEN_TAG . '3', $suggested, 'must skip suffixes that are themselves already taken, not just try +1 once');
            $this->assertFalse($pageManager->tagExists($suggested));
        } finally {
            $pageManager->deleteOrphaned(self::TAKEN_TAG);
            $pageManager->deleteOrphaned(self::TAKEN_TAG . '2');
        }
    }

    public function testCollisionCheckIsTypeAgnostic()
    {
        // proves the check works "regardless of which Content type currently holds the
        // colliding tag" using a real, already-existing different Content type (a bazar
        // entry) rather than a stub, since forms/users aren't Content yet (tickets 05/06) but
        // bazar entries already are -- same `pages` table, different `handler`/type marker
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $formManager = $wiki->services->get(FormManager::class);
        $entryManager = $wiki->services->get(EntryManager::class);

        $formManager->create([
            'id' => self::BAZAR_FORM_ID,
            'label' => 'PageManagerTagCollisionTest form',
            'template' => '',
            'condition' => '',
        ]);

        try {
            $entryManager->create(self::BAZAR_FORM_ID, [
                'antispam' => 1,
                'bf_titre' => 'Test entry',
                'tag' => self::BAZAR_ENTRY_TAG,
            ]);
            $this->assertTrue($entryManager->isEntry(self::BAZAR_ENTRY_TAG));

            // a bazar entry is Content the same way an ordinary page is (same `pages` table)
            // -- the tag-collision check must not special-case either
            $this->assertTrue($pageManager->tagExists(self::BAZAR_ENTRY_TAG));
            $suggested = $pageManager->suggestFreeTag(self::BAZAR_ENTRY_TAG);

            $this->assertSame(self::BAZAR_ENTRY_TAG . '2', $suggested);
            $this->assertFalse($pageManager->tagExists($suggested));
        } finally {
            $entryManager->delete(self::BAZAR_ENTRY_TAG, true);
            $formManager->delete(self::BAZAR_FORM_ID);
        }
    }
}
