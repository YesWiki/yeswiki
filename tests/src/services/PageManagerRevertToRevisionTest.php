<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Regression tests for ticket 03 (ACLs into metadata; selective vs. */
class PageManagerRevertToRevisionTest extends YesWikiTestCase
{
    private const TAG = 'PageManagerRevertToRevisionRegressionPage';
    private const OTHER_TAG = 'PageManagerRevertToRevisionRegressionOtherPage';

    public function testDefaultRevertDoesNotReopenAnAclTightenedAfterTheRevertedContentEdit()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'v1 body, open to everyone'], '', true);
            $v1 = $pageManager->getOne(self::TAG);

            $pageManager->save(self::TAG, [PageBody::CONTENT => 'v2 body, contains something sensitive'], '', true);
            $pageManager->setMetadata(self::TAG, ['acls' => ['read' => '@admins']]);
            $this->assertSame('@admins', $pageManager->getOne(self::TAG)['metadatas']['acls']['read'] ?? null);

            $pageManager->revertToRevision(self::TAG, $v1['id']);

            $current = $pageManager->getOne(self::TAG);
            $this->assertSame('v1 body, open to everyone', PageBody::content($current['body']));
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
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'v1 body'], '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'margot']);
            $v1 = $pageManager->getOne(self::TAG);

            $pageManager->save(self::TAG, [PageBody::CONTENT => 'v2 body'], '', true);
            $pageManager->setMetadata(self::TAG, ['theme' => 'colibris']);

            $pageManager->revertToRevision(self::TAG, $v1['id'], fullRevert: true);

            $current = $pageManager->getOne(self::TAG);
            $this->assertSame('v1 body', PageBody::content($current['body']));
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
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'v1 body'], '', true);
            $v1 = $pageManager->getOne(self::TAG);
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'v2 body'], '', true);

            $revisionsBefore = count($pageManager->getRevisions(self::TAG));
            $pageManager->revertToRevision(self::TAG, $v1['id'], fullRevert: true);

            $this->assertCount($revisionsBefore + 1, $pageManager->getRevisions(self::TAG));
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
        }
    }

    public function testCannotRevertAPageUsingAnotherPagesRevisionId()
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        try {
            $pageManager->save(self::TAG, [PageBody::CONTENT => 'page A content'], '', true);
            $pageManager->save(self::OTHER_TAG, [PageBody::CONTENT => 'page B content'], '', true);
            $otherRevision = $pageManager->getOne(self::OTHER_TAG);

            $this->expectException(\Exception::class);
            $pageManager->revertToRevision(self::TAG, $otherRevision['id']);
        } finally {
            $pageManager->deleteOrphaned(self::TAG);
            $pageManager->deleteOrphaned(self::OTHER_TAG);
        }
    }
}
