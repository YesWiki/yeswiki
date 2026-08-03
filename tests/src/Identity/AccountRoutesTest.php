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

/**
 * The account as routes (`/user`), replacing the navbar's login modal.
 *
 * What is worth testing here is not the markup but who gets shown what, because every one
 * of these routes is deliberately `public` -- a signed-out visitor has to be able to reach
 * them to sign in at all -- and the gate is therefore inside the controller rather than in
 * the route's ACL. Get that wrong in either direction and it is invisible until someone
 * either sees another person's account screen or cannot find the signup form.
 */
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
            // `@registered` here would answer someone who has not signed in with a refusal,
            // and a refusal is not a sign-in form
            $this->assertContains('public', $acl, "{$path} must be reachable without an account");
        }

        // a page tagged `user` would have no URL at all
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
            // and must NOT have rendered what it is for: `getLoggedUserName()` answers with
            // the client's IP when nobody is signed in, so a controller asking IT whether
            // there is a session shows every anonymous visitor the account screen
            $this->assertStringNotContainsString(
                'usersettings_action" value="update',
                $body,
                "/user/{$method} rendered account content to a signed-out visitor"
            );
        }

        // ... and it comes with no rail at all: a sidebar on the way in is a list of places
        // you cannot go yet, taking width from the one form the screen exists for
        $signIn = (string)$account->account()->getContent();
        $this->assertStringContainsString('yw-account-guest__card', $signIn, 'the sign-in form is a centred card');
        $this->assertStringNotContainsString('yw-dashboard__sidebar', $signIn, 'no rail before you sign in');

        // the two screens the sign-in form links to must be themselves, not it
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
            // the rail on an account screen lists the account and NOTHING else: your account
            // and the wiki's administration are two different errands, and the dashboard is
            // one navbar button away
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

            // signing up when you are already signed in is a screen with nothing to do
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
     * A routed screen renders its actions through Twig, and Twig wraps ANYTHING that escapes
     * a template in a RuntimeError. `{{login}}` ends a successful sign-in by throwing
     * ExitException (Redirector), so what reaches the kernel's exception listener is a
     * RuntimeError *carrying* it -- and an `instanceof` on the throwable itself misses,
     * which turned every sign-in through /user into a 500 with a stack trace in it.
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
