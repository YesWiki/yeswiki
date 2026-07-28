<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Core\Service\AuthenticationService;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\CommentService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 15 (security-core-split): addCommentIfAuthorized()'s own
 * inline hashcash check still required 'tools/security/secret/wp-hashcash.lib' directly
 * after that path was deleted by the same commit, so any comment submitted with
 * use_hashcash on would hit a fatal require_once error instead of the intended
 * "maybe you are a robot" rejection. Fixed by delegating to HashCashService::checkHashcash()
 * like every other hashcash call site.
 */
#[CoversMethod(CommentService::class, 'addCommentIfAuthorized')]
class CommentServiceHashcashTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'CommentServiceHashcashRegressionPage';

    public function testWikiExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(CommentService::class));

        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save(self::PAGE_TAG, 'content', '', true);
        $wiki->services->get(AclService::class)->save(self::PAGE_TAG, 'comment', '*');

        return $wiki;
    }

    #[Depends('testWikiExisting')]
    public function testAddCommentIfAuthorizedRejectsWithoutFatalingWhenHashcashEnabledAndValueMissing(Wiki $wiki)
    {
        $wiki->config['use_hashcash'] = true;
        $wiki->request->request->remove('hashcash_value');
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->UserIsAdmin($u['name'])));
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
    public function testAddCommentIfAuthorizedAcceptsWhenHashcashDisabled(Wiki $wiki)
    {
        $wiki->config['use_hashcash'] = false;
        $wiki->request->request->remove('hashcash_value');
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->UserIsAdmin($u['name'])));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise the comment path');

        // addCommentIfAuthorized()'s success path renders the new comment via $GLOBALS['wiki'],
        // normally populated by the production HTTP bootstrap, not the test harness (same
        // workaround as EditHandlerSaveTest/FiltertagsActionTest)
        $GLOBALS['wiki'] = $wiki;

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
