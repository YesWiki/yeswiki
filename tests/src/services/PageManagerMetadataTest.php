<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 02 (versioned metadata column on pages):
 * metadata is now a column on `pages`, versioned the same way `body` is, rather than
 * a standalone, non-versioned triple.
 */
class PageManagerMetadataTest extends YesWikiTestCase
{
    private const TAG = 'PageManagerMetadataRegressionPage';

    public function testMetadataIsCarriedForwardAcrossPlainContentEdits()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, 'first body', '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);

            // a plain content edit (no metadata change) must not wipe out metadata
            $pageManager->save(self::TAG, 'second body', '', true);
            $page = $pageManager->getOne(self::TAG);

            $this->assertSame('second body', $page['body']);
            $this->assertSame('margot', $page['metadatas']['theme'] ?? null);
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testRevertingToAPriorRevisionSeesThatRevisionsMetadataNotLatest()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            // time has only second-granularity; getOne($tag, $time) identifies a revision by
            // that column, so revisions created within the same wall-clock second are
            // ambiguous to look up individually -- force real gaps so every revision below
            // gets a distinct time, same as real edits a moment apart would
            $pageManager->save(self::TAG, 'v1 body', '', true);
            sleep(1);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);
            $v1 = $pageManager->getOne(self::TAG);

            sleep(1);

            // metadata changes independently of content: a later revision changes theme,
            // without touching body
            $pageManager->setMetadata(self::TAG, ['theme' => 'colibris']);
            $latest = $pageManager->getOne(self::TAG);
            $this->assertSame('colibris', $latest['metadatas']['theme'] ?? null);

            // reading the *prior* revision by its own timestamp must see that revision's
            // metadata (margot), not the current one (colibris) -- this is the actual bug
            // ticket 02 exists to fix (getOne() used to always call getMetadata($tag), which
            // ignored $time and always returned the latest, non-versioned triple)
            $reverted = $pageManager->getOne(self::TAG, $v1['time']);
            $this->assertSame('margot', $reverted['metadatas']['theme'] ?? null);
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testSetMetadataMergesRatherThanReplaces()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, 'body', '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot', 'style' => 'default.css']);
            $pageManager->setMetadata(self::TAG, ['theme' => 'colibris']);

            $page = $pageManager->getOne(self::TAG);

            $this->assertSame('colibris', $page['metadatas']['theme'] ?? null);
            $this->assertSame('default.css', $page['metadatas']['style'] ?? null, 'setMetadata() should merge, not replace, matching the pre-existing partial-update contract every caller relies on');
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testGetMetadataReturnsNullWhenNoneSet()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, 'body', '', true);

            $this->assertNull($pageManager->getMetadata(self::TAG));
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testSetMetadataIsANoOpWhenNothingActuallyChanges()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, 'body', '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);
            $revisionsAfterFirstSet = count($pageManager->getRevisions(self::TAG));

            // re-setting the exact same value must not create a spurious revision,
            // matching save()'s "don't save if body didn't change" guard
            $result = $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);

            $this->assertFalse($result);
            $this->assertCount($revisionsAfterFirstSet, $pageManager->getRevisions(self::TAG));
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }
}
