<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Api\CommentApiController;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 35: posting a comment must work with JavaScript off.
 *
 * `templates/comment-form.twig` is a plain `<form method="POST">` aimed at `/api/comments`. With
 * JavaScript a click handler intercepts it and posts by fetch; without, the browser submits it
 * normally -- and the route answered JSON, so the reader's whole page became
 * `{"code":200,"success":"..."}`. The comment was saved; the reader was left staring at a JSON
 * document with no way back. That was true *before* the `addcomment` handler was deleted, so the
 * handler was not what made this work.
 *
 * The two paths are told apart by `X-Requested-With`, which `postForm()` in yeswiki-base.js sends.
 * There is nothing else to go on: fetch sets no header a form submission does not also send, and
 * `Accept` is `*​/*` either way.
 */
#[CoversMethod(CommentApiController::class, 'postComment')]
class CommentWithoutJavascriptTest extends YesWikiTestCase
{
    private const TAG = 'TestTicket35CommentTarget';

    /** @return array{controller: CommentApiController, cleanup: callable} */
    private function scenario(bool $withXhrHeader): array
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $pageManager = $wiki->services->get(PageManager::class);

        // a page that accepts comments, and someone allowed to leave one. The comment ACL has to
        // be set explicitly: a fresh page does not accept comments from just anyone, so without
        // this the service answers 403 and the JSON branch never gets exercised.
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
        // replace(), not set(): CurrentRequest is the synthetic holder the kernel fills at boot
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

        // ExitException specifically, NOT \Throwable. Catching Throwable here swallows PHPUnit's
        // own failure exception from fail(), so the test passes even when the redirect branch is
        // removed -- which is exactly what happened the first time this was written, and what
        // mutation-testing it caught.
        try {
            $redirected = false;
            try {
                $controller->postComment();
            } catch (\YesWiki\Kernel\Exception\ExitException) {
                // Redirector::redirect() ends the request by throwing; that is the success signal
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
            // Asserted on the *shape*, not on a successful save. Whether this particular comment
            // is accepted is CommentService's business -- hashcash, comment ACLs, an empty body --
            // and it is covered where those rules live. What belongs here is the branch this test
            // exists for: an XHR post is answered with a JSON document rather than redirected.
            $response = $controller->postComment();

            $payload = json_decode((string)$response->getContent(), true);
            $this->assertIsArray($payload, 'an XHR post must be answered with JSON');
            $this->assertArrayHasKey('code', $payload, 'and with the payload the page script reads');
        } finally {
            $cleanup();
        }
    }
}
