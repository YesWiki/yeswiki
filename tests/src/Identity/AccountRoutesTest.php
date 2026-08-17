<?php

namespace YesWiki\Test\Identity;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Error\RuntimeError;
use YesWiki\Identity\Controller\AccountController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\RouteProvider;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/** The account as routes (`/user`), replacing the navbar's login modal. */
class AccountRoutesTest extends YesWikiTestCase
{
    private const PROBE = 'AccountRoutesTestUser';

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(AccountController::class), 'the account routes need their controller');

        return $wiki;
    }

    #[Depends('testWikiExisting')]
    public function testEveryAccountRouteIsReachableSignedOut(YesWikiRuntime $wiki): void
    {
        $routes = [];
        foreach ($wiki->services->get(RouteProvider::class)->get() as $route) {
            if (str_starts_with($route->getPath(), '/user')) {
                $routes[$route->getPath()] = (array)$route->getOption('acl');
            }
        }

        $this->assertNotEmpty($routes, 'the account routes must be discoverable');
        foreach ($routes as $path => $acl) {
            $this->assertContains('public', $acl, "{$path} must be reachable without an account");
        }

        $this->assertTrue(ReservedTags::isReserved('user'));
        $this->assertTrue(ReservedTags::isReserved('User'), 'reserving is case-insensitive');
        $this->assertTrue(ReservedTags::isReserved('user/pages'), 'the first segment decides');
    }

    #[Depends('testWikiExisting')]
    public function testSignedOutSeesTheSignInFormOnEveryAccountScreen(YesWikiRuntime $wiki): void
    {
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $wiki->services->get(AuthenticationService::class)->logout();
        $account = $wiki->services->get(AccountController::class);

        foreach (['account', 'pages', 'entries', 'reactions'] as $method) {
            $body = (string)$account->{$method}()->getContent();
            $this->assertStringContainsString('login-form', $body, "/user/{$method} must offer the sign-in form");

            $this->assertStringNotContainsString(
                'usersettings_action" value="update',
                $body,
                "/user/{$method} rendered account content to a signed-out visitor"
            );
        }

        $signIn = (string)$account->account()->getContent();
        $this->assertStringContainsString('yw-account-guest__card', $signIn, 'the sign-in form is a centred card');
        $this->assertStringNotContainsString('yw-dashboard__sidebar', $signIn, 'no rail before you sign in');

        $signup = (string)$account->signup()->getContent();
        $this->assertStringContainsString('usersettings_action" value="signup"', $signup);
        $this->assertStringNotContainsString('login-form', $signup, 'the signup form is not the sign-in form');
    }

    #[Depends('testWikiExisting')]
    public function testSignedInSeesTheirAccountAndTheRail(YesWikiRuntime $wiki): void
    {
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $userManager = $wiki->services->get(UserManager::class);
        $authentication = $wiki->services->get(AuthenticationService::class);

        $user = $userManager->getOneByName(self::PROBE);
        if (empty($user)) {
            $userManager->create(self::PROBE, 'account-routes@example.tld', 'Aa1!aaaaProbe');
            $user = $userManager->getOneByName(self::PROBE);
        }
        $this->assertNotEmpty($user, 'the account screens need an account to render for');

        $authentication->login($user);
        try {
            $account = $wiki->services->get(AccountController::class);

            $body = (string)$account->account()->getContent();
            $this->assertStringNotContainsString('login-form', $body, 'a signed-in visitor gets their account');
            $this->assertStringContainsString('usersettings_action" value="update"', $body);

            $this->assertStringContainsString('?user/pages', $body, 'the rail links the account screens');
            $this->assertStringNotContainsString('?dashboard/', $body, 'the account rail is not the dashboard');
            $this->assertStringNotContainsString('?admin/', $body, 'the account rail is not the admin rail');

            foreach (['pages', 'entries', 'reactions'] as $method) {
                $this->assertStringNotContainsString(
                    'login-form',
                    (string)$account->{$method}()->getContent(),
                    "/user/{$method} must render for a signed-in visitor"
                );
            }

            $this->assertInstanceOf(RedirectResponse::class, $account->signup());
            $this->assertInstanceOf(RedirectResponse::class, $account->lostPassword());

            $logout = $account->logout();
            $this->assertInstanceOf(RedirectResponse::class, $logout);
            $this->assertSame(302, $logout->getStatusCode());
            $this->assertEmpty($authentication->getLoggedUser(), 'logging out must end the session');
        } finally {
            $authentication->logout();
            $probe = $userManager->getOneByName(self::PROBE);
            if (!empty($probe)) {
                $userManager->delete($probe);
            }
        }
    }

    /**
     * A routed screen renders its actions through Twig, and Twig wraps ANYTHING that escapes a template in a RuntimeError.
     */
    #[Depends('testWikiExisting')]
    public function testARedirectFromInsideATemplateIsNotAnError(YesWikiRuntime $wiki): void
    {
        $wrapped = new RuntimeError(
            'An exception has been thrown during the rendering of a template',
            15,
            null,
            new ExitException('')
        );

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $wrapped
        );
        $wiki->onDispatchException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertNotSame(500, $response->getStatusCode(), 'a redirect is not a server error');
        $this->assertStringNotContainsString('exceptionMessage', (string)$response->getContent());
    }
}
