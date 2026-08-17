<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Social\Api\CommentApiController;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 35: posting a comment must work with JavaScript off. */
#[CoversMethod(CommentApiController::class, 'postComment')]
class CommentWithoutJavascriptTest extends YesWikiTestCase
{
    private const TAG = 'TestTicket35CommentTarget';

    /**
     * @return array{controller: CommentApiController, cleanup: callable}
     */
    private function scenario(bool $withXhrHeader): array
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $pageManager->save(self::TAG, ['content' => 'a page to comment on'], '', true);
        $wiki->services->get(\YesWiki\Identity\Service\AclService::class)->save(self::TAG, 'comment', '+');
        $name = 'CommentTester' . bin2hex(random_bytes(4));
        $userManager->create($name, strtolower($name) . '@example.com', 'a-solid-password');
        $user = self::requireUser($userManager->getOneByName($name));
        $authenticationService->login($user);

        $headers = $withXhrHeader ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : [];
        $request = Request::create(
            '/?api/comments',
            'POST',
            ['pagetag' => self::TAG, 'body' => 'a comment left without javascript', 'action' => 'addcomment'],
            [],
            [],
            $headers
        );

        $previous = $wiki->services->get(CurrentRequest::class)->get();
        $wiki->services->get(CurrentRequest::class)->replace($request);

        $controller = $wiki->services->get(CommentApiController::class);

        return [
            'controller' => $controller,
            'cleanup' => function () use ($wiki, $userManager, $authenticationService, $name, $previous): void {
                $wiki->services->get(CurrentRequest::class)->replace($previous);
                $authenticationService->logout();
                $existing = $userManager->getOneByName($name);
                if (!empty($existing)) {
                    $userManager->delete($existing);
                }
                $wiki->services->get(PageManager::class)->deleteOrphaned(self::TAG);
            },
        ];
    }

    public function testAFormPostIsRedirectedBackToThePage(): void
    {
        ['controller' => $controller, 'cleanup' => $cleanup] = $this->scenario(false);

        try {
            $redirected = false;
            try {
                $controller->postComment();
            } catch (\YesWiki\Kernel\Exception\ExitException) {
                $redirected = true;
            }

            $this->assertTrue(
                $redirected,
                'a plain form post must be redirected back to the page, not answered with JSON'
            );
        } finally {
            $cleanup();
        }
    }

    public function testAnXhrPostStillGetsJson(): void
    {
        ['controller' => $controller, 'cleanup' => $cleanup] = $this->scenario(true);

        try {
            $response = $controller->postComment();

            $payload = json_decode((string)$response->getContent(), true);
            $this->assertIsArray($payload, 'an XHR post must be answered with JSON');
            $this->assertArrayHasKey('code', $payload, 'and with the payload the page script reads');
        } finally {
            $cleanup();
        }
    }
}
