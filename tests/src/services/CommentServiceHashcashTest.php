<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Social\Service\CommentService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 15 (security-core-split): addCommentIfAuthorized()'s own inline hashcash check still required 'tools/security/secret/wp-hashcash.lib' directly after that path was deleted by the same commit, so any comment submitted with use_hashcash on would hit a fatal require_once error instead of the intended "maybe you are a robot" rejection.
 */
#[CoversMethod(CommentService::class, 'addCommentIfAuthorized')]
class CommentServiceHashcashTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'CommentServiceHashcashRegressionPage';

    /**
     * The success case below posts a real comment, and a comment is a page row: without this the suite left one behind on every run, 786 of them by the time anyone looked.
     */
    public static function tearDownAfterClass(): void
    {
        $wiki = self::getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $indexer = $wiki->services->get(\YesWiki\Search\Service\SearchIndexer::class);

        foreach ($wiki->services->get(CommentService::class)->loadComments(self::PAGE_TAG, true) as $comment) {
            $indexer->delete((string)($comment['tag'] ?? ''));
        }
        $indexer->delete(self::PAGE_TAG);
        $pageManager->deleteOrphaned(self::PAGE_TAG);
    }

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(CommentService::class));

        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => 'content'], '', true);
        $wiki->services->get(AclService::class)->save(self::PAGE_TAG, 'comment', '*');

        return $wiki;
    }

    #[Depends('testWikiExisting')]
    public function testAddCommentIfAuthorizedRejectsWithoutFatalingWhenHashcashEnabledAndValueMissing(YesWikiRuntime $wiki): void
    {
        $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['use_hashcash'] = true;
        $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->remove('hashcash_value');
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise the comment path');

        try {
            $authenticationService->login($admin);
            $commentService = $wiki->services->get(CommentService::class);

            $result = $commentService->addCommentIfAuthorized([
                'pagetag' => self::PAGE_TAG,
                'body' => 'a comment',
            ]);

            $this->assertSame(400, $result['code']);
            $this->assertSame(_t('HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT'), $result['error']);
        } finally {
            $authenticationService->logout();
        }
    }

    #[Depends('testWikiExisting')]
    public function testAddCommentIfAuthorizedAcceptsWhenHashcashDisabled(YesWikiRuntime $wiki): void
    {
        $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['use_hashcash'] = false;
        $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->remove('hashcash_value');
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(AclService::class)->isAdmin($u['name'])));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise the comment path');

        $GLOBALS['yeswikiServices'] = $wiki->services;

        try {
            $authenticationService->login($admin);
            $commentService = $wiki->services->get(CommentService::class);

            $result = $commentService->addCommentIfAuthorized([
                'pagetag' => self::PAGE_TAG,
                'body' => 'a comment',
            ]);

            $this->assertNotSame(_t('HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT'), $result['error'] ?? null);
        } finally {
            $authenticationService->logout();
            unset($GLOBALS['wiki']);
        }
    }
}
