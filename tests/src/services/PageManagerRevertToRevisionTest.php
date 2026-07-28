<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 03 (ACLs into metadata; selective vs. full revert):
 * PageManager::revertToRevision() is selective by default (content only) and only restores
 * metadata (including ACLs) too when $fullRevert is explicitly true.
 */
class PageManagerRevertToRevisionTest extends YesWikiTestCase
{
    private const TAG = 'PageManagerRevertToRevisionRegressionPage';
    private const OTHER_TAG = 'PageManagerRevertToRevisionRegressionOtherPage';

    public function testDefaultRevertDoesNotReopenAnAclTightenedAfterTheRevertedContentEdit()
    {
        // the exact scenario ticket 03 names explicitly: a page's ACL is tightened after a
        // content edit; a later default revert to the earlier wording must NOT reopen the
        // earlier, looser ACL
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, 'v1 body, open to everyone', '', true);
            $v1 = $pageManager->getOne(self::TAG);

            $pageManager->save(self::TAG, 'v2 body, contains something sensitive', '', true);
            $pageManager->setMetadata(self::TAG, ['acls' => ['read' => '@admins']]);
            $this->assertSame('@admins', $pageManager->getOne(self::TAG)['metadatas']['acls']['read'] ?? null);

            // an admin (or automated process) reverts the wording back to v1, without
            // intending to also reopen access
            $pageManager->revertToRevision(self::TAG, $v1['id']);

            $current = $pageManager->getOne(self::TAG);
            $this->assertSame('v1 body, open to everyone', $current['body']);
            $this->assertSame(
                '@admins',
                $current['metadatas']['acls']['read'] ?? null,
                'a selective (default) revert must not reopen an ACL tightened after the reverted content edit'
            );
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testFullRevertRestoresMetadataToo()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, 'v1 body', '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);
            $v1 = $pageManager->getOne(self::TAG);

            $pageManager->save(self::TAG, 'v2 body', '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'colibris']);

            $pageManager->revertToRevision(self::TAG, $v1['id'], fullRevert: true);

            $current = $pageManager->getOne(self::TAG);
            $this->assertSame('v1 body', $current['body']);
            $this->assertSame(
                'margot',
                $current['metadatas']['theme'] ?? null,
                'an explicit full revert must restore the target revision\'s exact metadata, including ACLs'
            );
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testFullRevertDoesNotCreateASecondRevision()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, 'v1 body', '', true);
            $v1 = $pageManager->getOne(self::TAG);
            $pageManager->save(self::TAG, 'v2 body', '', true);

            $revisionsBefore = count($pageManager->getRevisions(self::TAG));
            $pageManager->revertToRevision(self::TAG, $v1['id'], fullRevert: true);

            // one revert = one new revision, whether or not it's a full revert -- restoring
            // metadata shouldn't cost a second insert on top of the content revert's
            $this->assertCount($revisionsBefore + 1, $pageManager->getRevisions(self::TAG));
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testCannotRevertAPageUsingAnotherPagesRevisionId()
    {
        // regression for a real, pre-existing ACL-bypass this refactor fixed: the old
        // RevisionsHandler checked write access against the *currently viewed* page but then
        // saved to $page['tag'] -- the *target revision's* page, from an attacker-controlled
        // restoreRevisionId. A user with write access to page A could revert page B (which
        // they might have no write access to at all) to an arbitrary prior revision.
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, 'page A content', '', true);
            $pageManager->save(self::OTHER_TAG, 'page B content', '', true);
            $otherRevision = $pageManager->getOne(self::OTHER_TAG);

            $this->expectException(\Exception::class);
            $pageManager->revertToRevision(self::TAG, $otherRevision['id']);
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
            $pageManager->deleteOrphaned(self::OTHER_TAG);
        }
    }
}
