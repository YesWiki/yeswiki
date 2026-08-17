<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 02 (versioned metadata column on pages): metadata is now a column on `pages`, versioned the same way `body` is, rather than a standalone, non-versioned triple.
 */
class PageManagerMetadataTest extends YesWikiTestCase
{
    private const TAG = 'PageManagerMetadataRegressionPage';

    public function testMetadataIsCarriedForwardAcrossPlainContentEdits()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'first body'], '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);

            $pageManager->save(self::TAG, [PageBody::CONTENT => 'second body'], '', true);
            $page = $pageManager->getOne(self::TAG);

            $this->assertSame('second body', PageBody::content($page['body']));
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
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'v1 body'], '', true);
            sleep(1);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);
            $v1 = $pageManager->getOne(self::TAG);

            sleep(1);

            $pageManager->setMetadata(self::TAG, ['theme' => 'colibris']);
            $latest = $pageManager->getOne(self::TAG);
            $this->assertSame('colibris', $latest['metadatas']['theme'] ?? null);

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
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'body'], '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot', 'style' => 'default.css']);
            $pageManager->setMetadata(self::TAG, ['theme' => 'colibris']);

            $page = $pageManager->getOne(self::TAG);

            $this->assertSame('colibris', $page['metadatas']['theme'] ?? null);
            $this->assertSame('default.css', $page['metadatas']['style'] ?? null, 'setMetadata() should merge, not replace, matching the pre-existing partial-update contract every caller relies on');
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testGetMetadataHasNoExtraKeysBeyondDefaultAclsWhenNoneExplicitlySet()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'body'], '', true);

            $metadata = $pageManager->getMetadata(self::TAG);

            $this->assertSame(['acls'], array_keys($metadata ?? []));
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testSetMetadataIsANoOpWhenNothingActuallyChanges()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'body'], '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);
            $revisionsAfterFirstSet = count($pageManager->getRevisions(self::TAG));

            $result = $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);

            $this->assertFalse($result);
            $this->assertCount($revisionsAfterFirstSet, $pageManager->getRevisions(self::TAG));
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }
}
