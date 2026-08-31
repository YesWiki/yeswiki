<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\CommentService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A comment edit is authorized against the comment it rewrites, never against the parent page
 * named in the request.
 */
class CommentServiceTest extends YesWikiTestCase
{
    private const VICTIM = 'CommentServiceVictimPage';
    private const OWN = 'CommentServiceOwnPage';
    private const COMMENT = 'Comment900001';
    private const AUTHOR = 'CommentServiceAuthor';
    private const STRANGER = 'CommentServiceStranger';

    private $wiki;
    private $pageManager;
    private $aclService;
    private $commentService;
    private $userManager;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->aclService = $this->wiki->services->get(AclService::class);
        $this->commentService = $this->wiki->services->get(CommentService::class);
        $this->userManager = $this->wiki->services->get(UserManager::class);

        $this->user(self::AUTHOR);
        $this->user(self::STRANGER);

        $this->logIn(self::STRANGER);
        $this->page(self::VICTIM, self::STRANGER, '@admins');
        $this->logIn(self::AUTHOR);
        $this->page(self::OWN, self::AUTHOR, '+');

        $this->pageManager->save(self::COMMENT, 'a comment', self::OWN, true);
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
        $this->wiki->services->get(AuthController::class)->logout();
    }

    private function user(string $name): void
    {
        if (!$this->userManager->getOneByName($name)) {
            $this->userManager->create([
                'name' => $name,
                'email' => strtolower($name) . '@example.com',
                'password' => 'a-long-enough-password',
            ]);
        }
    }

    private function logIn(string $name): void
    {
        $this->wiki->services->get(AuthController::class)->login($this->userManager->getOneByName($name));
    }

    private function page(string $tag, string $owner, string $commentAcl): void
    {
        $this->pageManager->save($tag, "the body of $tag", '', true);
        $this->aclService->save($tag, 'read', '*');
        $this->aclService->save($tag, 'write', $owner);
        $this->aclService->save($tag, 'comment', $commentAcl);
    }

    /**
     * @return array<string,string>
     */
    private function post(string $parent, string $body): array
    {
        $content = ['pagetag' => $parent, 'body' => $body];
        if (!empty($this->wiki->config['use_hashcash'])) {
            require_once 'tools/security/secret/wp-hashcash.lib';
            $content['hashcash_value'] = hashcash_field_value();
        }

        return $content;
    }

    private function body(string $tag): ?string
    {
        return $this->pageManager->getOne($tag, null, false, true)['body'] ?? null;
    }

    public function testAPageCannotBeOverwrittenThroughACommentEdit()
    {
        $result = $this->commentService->addCommentIfAuthorized($this->post(self::OWN, 'hijacked'), self::VICTIM);

        $this->assertSame(404, $result['code']);
        $this->assertSame('the body of ' . self::VICTIM, $this->body(self::VICTIM));
        $this->assertSame('', $this->pageManager->getOne(self::VICTIM, null, false, true)['comment_on']);
    }

    public function testACommentTagThatDoesNotExistIsNotCreatedFromScratch()
    {
        $result = $this->commentService->addCommentIfAuthorized($this->post(self::OWN, 'hijacked'), 'Comment900002');

        $this->assertSame(404, $result['code']);
        $this->assertNull($this->body('Comment900002'));
    }

    public function testSomebodyElsesCommentCannotBeRewritten()
    {
        $this->logIn(self::STRANGER);

        $result = $this->commentService->addCommentIfAuthorized($this->post(self::OWN, 'hijacked'), self::COMMENT);

        $this->assertSame(403, $result['code']);
        $this->assertSame('a comment', $this->body(self::COMMENT));
    }

    public function testACommentCannotBeMovedToAnotherPage()
    {
        $result = $this->commentService->addCommentIfAuthorized($this->post(self::VICTIM, 'reparented'), self::COMMENT);

        $this->assertSame(200, $result['code']);
        $this->assertSame(self::OWN, $this->pageManager->getOne(self::COMMENT, null, false, true)['comment_on']);
    }

    public function testTheAuthorCanStillRewriteTheirOwnComment()
    {
        $result = $this->commentService->addCommentIfAuthorized($this->post(self::OWN, 'second thoughts'), self::COMMENT);

        $this->assertSame(200, $result['code']);
        $this->assertSame('second thoughts', $this->body(self::COMMENT));
    }
}
