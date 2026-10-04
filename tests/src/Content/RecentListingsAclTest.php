<?php

namespace YesWiki\Test\Content;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Action\MychangesAction;
use YesWiki\Content\Action\RecentChangesRssAction;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Social\Service\CommentService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Recent-change and recent-comment listings name only the pages the caller may read. */
class RecentListingsAclTest extends YesWikiTestCase
{
    private const PUBLIC_TAG = 'RecentListingsPublicPage';
    private const RESTRICTED_TAG = 'RecentListingsRestrictedPage';
    private const PUBLIC_COMMENT = 'Comment900101';
    private const RESTRICTED_COMMENT = 'Comment900102';
    private const EDITOR = 'RecentListingsEditor';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private PageManager $pageManager;
    private AclService $aclService;
    private UserManager $userManager;
    private AuthenticationService $authenticationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);
        $this->userManager = $this->wiki->services->get(UserManager::class);
        $this->authenticationService = $this->wiki->services->get(AuthenticationService::class);

        if (!$this->userManager->getOneByName(self::EDITOR)) {
            $this->userManager->create(self::EDITOR, strtolower(self::EDITOR) . '@example.com', 'a-long-enough-password');
        }
        $editor = $this->userManager->getOneByName(self::EDITOR);
        $this->assertNotNull($editor);
        $this->authenticationService->login($editor);

        $this->pageManager->save(self::PUBLIC_TAG, [PageBody::CONTENT => 'public content'], '', true);
        $this->aclService->save(self::PUBLIC_TAG, 'read', '*');
        $this->pageManager->save(self::RESTRICTED_TAG, [PageBody::CONTENT => 'secret content'], '', true);
        $this->aclService->save(self::RESTRICTED_TAG, 'read', '@admins');
        $this->pageManager->save(self::PUBLIC_COMMENT, [PageBody::CONTENT => 'public comment'], self::PUBLIC_TAG, true);
        $this->pageManager->save(self::RESTRICTED_COMMENT, [PageBody::CONTENT => 'secret comment'], self::RESTRICTED_TAG, true);

        $this->authenticationService->logout();
    }

    protected function tearDown(): void
    {
        foreach ([self::PUBLIC_COMMENT, self::RESTRICTED_COMMENT, self::PUBLIC_TAG, self::RESTRICTED_TAG] as $tag) {
            $this->pageManager->deleteOrphaned($tag);
            $this->aclService->delete($tag);
        }
        $this->authenticationService->logout();
        if ($user = $this->userManager->getOneByName(self::EDITOR)) {
            $this->userManager->delete($user);
        }
        parent::tearDown();
    }

    public function testRecentlyChangedSkipsUnreadablePages(): void
    {
        $tags = array_column($this->pageManager->getRecentlyChanged(50) ?? [], 'tag');

        $this->assertContains(self::PUBLIC_TAG, $tags);
        $this->assertNotContains(self::RESTRICTED_TAG, $tags);
    }

    public function testRecentlyChangedSinceADateSkipsUnreadablePages(): void
    {
        $tags = array_column($this->pageManager->getRecentlyChanged(50, '1970-01-01 00:00:00') ?? [], 'tag');

        $this->assertContains(self::PUBLIC_TAG, $tags);
        $this->assertNotContains(self::RESTRICTED_TAG, $tags);
    }

    public function testRecentCommentsSkipCommentsOnUnreadablePages(): void
    {
        $comments = $this->wiki->services->get(CommentService::class)->getRecentComments(50);
        $tags = array_column($comments, 'tag');

        $this->assertContains(self::PUBLIC_COMMENT, $tags);
        $this->assertNotContains(self::RESTRICTED_COMMENT, $tags);
        $this->assertNotContains(self::RESTRICTED_TAG, array_column($comments, 'parent'));
    }

    public function testRecentlyCommentedSkipsUnreadablePages(): void
    {
        $tags = array_column($this->wiki->services->get(CommentService::class)->getRecentlyCommented(50), 'tag');

        $this->assertContains(self::PUBLIC_TAG, $tags);
        $this->assertNotContains(self::RESTRICTED_TAG, $tags);
    }

    public function testMychangesSkipsPagesTheEditorCanNoLongerRead(): void
    {
        $editor = $this->userManager->getOneByName(self::EDITOR);
        $this->assertNotNull($editor);
        $this->authenticationService->login($editor);

        $output = $this->runAction(new MychangesAction(), []);

        $this->assertStringContainsString(self::PUBLIC_TAG, $output);
        $this->assertStringNotContainsString(self::RESTRICTED_TAG, $output);
    }

    public function testRssFeedLeavesUnreadablePagesOut(): void
    {
        $this->wiki->services->get(PageContext::class)->setMethod('xml');

        $output = $this->runAction(new RecentChangesRssAction(), ['link' => 'PagePrincipale']);

        $this->assertStringContainsString(self::PUBLIC_TAG, $output);
        $this->assertStringNotContainsString(self::RESTRICTED_TAG, $output);
        $this->assertStringNotContainsString(substr(self::RESTRICTED_TAG, 0, 3) . '___', $output);
    }

    /** @param array<string, mixed> $arguments */
    private function runAction(\YesWiki\Core\YesWikiAction $action, array $arguments): string
    {
        $output = '';
        $action->setServices($this->wiki->services);
        $action->setParams($this->wiki->services->get(ParameterBagInterface::class));
        $action->setTwig($this->wiki->services->get(TemplateEngine::class));
        $action->setArguments($arguments);
        $action->setOutput($output);

        return (string)$action->run();
    }
}
