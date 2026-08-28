<?php

namespace YesWiki\Test\Social;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Social\Service\CommentService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A comment edit is authorized against the comment it rewrites, never against the parent page named in the request (GHSA-894w-63wr-8x5r). */
class CommentServiceTest extends YesWikiTestCase
{
    private const VICTIM = 'CommentServiceVictimPage';
    private const OWN = 'CommentServiceOwnPage';
    private const COMMENT = 'Comment900001';
    private const AUTHOR = 'CommentServiceAuthor';
    private const STRANGER = 'CommentServiceStranger';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private PageManager $pageManager;
    private AclService $aclService;
    private CommentService $commentService;
    private UserManager $userManager;
    private mixed $hashcashWas = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);
        $this->commentService = $this->wiki->services->get(CommentService::class);
        $this->userManager = $this->wiki->services->get(UserManager::class);

        // the puzzle answer lives in a file this test has no way to read, and none of what it
        // checks is about hashcash
        $config = $this->wiki->services->get(RuntimeConfig::class);
        $this->hashcashWas = $config['use_hashcash'];
        $config['use_hashcash'] = false;

        $this->user(self::AUTHOR);
        $this->user(self::STRANGER);

        $this->logIn(self::STRANGER);
        $this->page(self::VICTIM, self::STRANGER, '@admins');
        $this->logIn(self::AUTHOR);
        $this->page(self::OWN, self::AUTHOR, '+');

        $this->pageManager->save(self::COMMENT, [PageBody::CONTENT => 'a comment'], self::OWN, true);
        $this->aclService->save(self::COMMENT, 'read', '*');
        $this->aclService->save(self::COMMENT, 'write', self::AUTHOR);
        $this->aclService->save(self::COMMENT, 'comment', '');
    }

    protected function tearDown(): void
    {
        foreach ([self::COMMENT, self::VICTIM, self::OWN] as $tag) {
            $this->pageManager->deleteOrphaned($tag);
            $this->aclService->delete($tag);
        }
        foreach ([self::AUTHOR, self::STRANGER] as $name) {
            if ($user = $this->userManager->getOneByName($name)) {
                $this->userManager->delete($user);
            }
        }
        $this->wiki->services->get(AuthenticationService::class)->logout();
        $this->wiki->services->get(RuntimeConfig::class)['use_hashcash'] = $this->hashcashWas;
        parent::tearDown();
    }

    private function user(string $name): void
    {
        if (!$this->userManager->getOneByName($name)) {
            $this->userManager->create($name, strtolower($name) . '@example.com', 'a-long-enough-password');
        }
    }

    private function logIn(string $name): void
    {
        $user = $this->userManager->getOneByName($name);
        $this->assertNotNull($user, "the fixture account $name was not created");
        $this->wiki->services->get(AuthenticationService::class)->login($user);
    }

    private function page(string $tag, string $owner, string $commentAcl): void
    {
        $this->pageManager->save($tag, [PageBody::CONTENT => "the body of $tag"], '', true);
        $this->aclService->save($tag, 'read', '*');
        $this->aclService->save($tag, 'write', $owner);
        $this->aclService->save($tag, 'comment', $commentAcl);
    }

    /** @return array<string, string> */
    private function post(string $parent, string $body): array
    {
        return ['pagetag' => $parent, 'body' => $body];
    }

    private function body(string $tag): ?string
    {
        $page = $this->pageManager->getOne($tag, null, false, true);

        return $page === null ? null : PageBody::content($page['body'] ?? []);
    }

    private function parentOf(string $tag): ?string
    {
        $page = $this->pageManager->getOne($tag, null, false, true);

        return $page === null ? null : ($page['parent'] ?? null);
    }

    public function testAPageCannotBeOverwrittenThroughACommentEdit(): void
    {
        $result = $this->commentService->addCommentIfAuthorized($this->post(self::OWN, 'hijacked'), self::VICTIM);

        $this->assertSame(404, $result['code']);
        $this->assertSame('the body of ' . self::VICTIM, $this->body(self::VICTIM));
        $this->assertSame('', $this->parentOf(self::VICTIM));
    }

    public function testACommentTagThatDoesNotExistIsNotCreatedFromScratch(): void
    {
        $result = $this->commentService->addCommentIfAuthorized($this->post(self::OWN, 'hijacked'), 'Comment900002');

        $this->assertSame(404, $result['code']);
        $this->assertNull($this->body('Comment900002'));
    }

    public function testSomebodyElsesCommentCannotBeRewritten(): void
    {
        $this->logIn(self::STRANGER);

        $result = $this->commentService->addCommentIfAuthorized($this->post(self::OWN, 'hijacked'), self::COMMENT);

        $this->assertSame(403, $result['code']);
        $this->assertSame('a comment', $this->body(self::COMMENT));
    }

    public function testACommentCannotBeMovedToAnotherPage(): void
    {
        $result = $this->commentService->addCommentIfAuthorized($this->post(self::VICTIM, 'reparented'), self::COMMENT);

        $this->assertSame(200, $result['code']);
        $this->assertSame(self::OWN, $this->parentOf(self::COMMENT));
    }

    public function testTheAuthorCanStillRewriteTheirOwnComment(): void
    {
        $result = $this->commentService->addCommentIfAuthorized($this->post(self::OWN, 'second thoughts'), self::COMMENT);

        $this->assertSame(200, $result['code']);
        $this->assertSame('second thoughts', $this->body(self::COMMENT));
    }
}
