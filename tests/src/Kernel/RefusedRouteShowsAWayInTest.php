<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\HttpFoundation\Response;
use YesWiki\Identity\Action\LoginAction;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `/admin/*` answered a signed-out visitor with "Not enough access rights" on a blank page.
 *
 * No chrome, no way in, and nothing saying that signing in was the answer. A screen that refuses
 * somebody has to tell them what to do about it, and what to do about it depends on whether they
 * are signed in already.
 */
class RefusedRouteShowsAWayInTest extends YesWikiTestCase
{
    /** @var array<string, mixed>|null */
    private ?array $sessionBefore = null;

    protected function setUp(): void
    {
        $this->sessionBefore = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBefore ?? [];
    }

    private function refuse(string $path): Response
    {
        $wiki = self::getWiki();
        $method = new \ReflectionMethod($wiki, 'accessRefused');
        $method->setAccessible(true);

        return $method->invoke($wiki, $path);
    }

    public function testASignedOutVisitorIsSentToSignInAndBack(): void
    {
        unset($_SESSION['user']);

        $response = $this->refuse('/admin/logs');

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());

        $location = (string)$response->headers->get('Location');
        $this->assertStringContainsString('user', $location, 'the sign-in screen');
        $this->assertStringContainsString(
            LoginAction::RETURN_PARAM . '=',
            $location,
            'carrying where they were going, so signing in takes them there'
        );
    }

    /** Offering a sign-in form for an account they are already using explains nothing. */
    public function testSomebodyAlreadySignedInIsToldTheyMayNot(): void
    {
        $_SESSION['user'] = ['name' => self::signedInName()];

        $response = $this->refuse('/admin/logs');

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString(_t('ERROR_NO_ACCESS'), (string)$response->getContent());
        $this->assertStringContainsString('<html', (string)$response->getContent(), 'with the wiki around it');
    }

    /** A client asking for JSON wants a status code, not a page it cannot read. */
    public function testAnApiRouteStillAnswersWithAStatusCode(): void
    {
        unset($_SESSION['user']);

        foreach (['/api/admin/logs', '/api'] as $path) {
            $response = $this->refuse($path);
            $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), $path);
            $this->assertStringNotContainsString('<html', (string)$response->getContent(), $path);
        }
    }

    /** Any account the wiki actually has: this is about being signed in, not about who. */
    private static function signedInName(): string
    {
        $users = self::getWiki()->services->get(\YesWiki\Identity\Service\UserManager::class)->getAll();

        return (string)($users[0]['name'] ?? '');
    }
}
