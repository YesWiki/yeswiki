<?php

namespace YesWiki\Identity\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/user` -- your account, and the way in when you have none open.
 *
 * Signing in used to happen in a modal built into the navbar: the form was on every page of
 * the wiki whether or not anyone would ever use it, its markup sat inside `#yw-topnav` on
 * some renders and outside it on others, and everything you might do with an account
 * afterwards lived somewhere else again -- `ParametresUtilisateur`, `MesContenus`,
 * `MotDePassePerdu`, seeded wiki pages any install could rename or delete.
 *
 * This is the same move `/dashboard` made: routes instead of pages, one rail, one place.
 * The account section of that rail appears once you are signed in, so /user is both the
 * sign-in screen and everything behind it.
 *
 * ## Why every route here is `public`
 *
 * The ACL option gates the *route*; a route gated on `@registered` would answer an
 * anonymous visitor with a refusal, and a refusal is not what someone who simply has not
 * signed in yet needs to see. So the routes are open and each screen decides: signed in, it
 * renders itself; signed out, it renders the sign-in form in the same shell. Nothing behind
 * them leaks -- the actions they render (`usersettings`, `mypages`, `userreactions`) show a
 * signed-out visitor nothing of their own accord either.
 */
class AccountController extends YesWikiController
{
    use DashboardShell;

    #[Route('/user', options: ['acl' => ['public']])]
    public function account(): Response
    {
        return $this->page('@core/account/settings.twig', 'user');
    }

    #[Route('/user/pages', options: ['acl' => ['public']])]
    public function pages(): Response
    {
        return $this->page('@core/account/pages.twig', 'user/pages');
    }

    #[Route('/user/entries', options: ['acl' => ['public']])]
    public function entries(): Response
    {
        return $this->page('@core/account/entries.twig', 'user/entries');
    }

    #[Route('/user/reactions', options: ['acl' => ['public']])]
    public function reactions(): Response
    {
        return $this->page('@core/account/reactions.twig', 'user/reactions');
    }

    /**
     * Creating an account -- the other half of the sign-in screen.
     *
     * `{{usersettings}}` answers a signed-out visitor with the signup form and a signed-in
     * one with their settings, which would make this route silently become /user for
     * anyone who already has an account. It says so instead.
     */
    #[Route('/user/signup', options: ['acl' => ['public']])]
    public function signup(): Response
    {
        if ($this->isConnected()) {
            return $this->toAccount();
        }

        return $this->openPage('@core/account/signup.twig', 'user/signup');
    }

    #[Route('/user/lost-password', options: ['acl' => ['public']])]
    public function lostPassword(): Response
    {
        if ($this->isConnected()) {
            return $this->toAccount();
        }

        return $this->openPage('@core/account/lost-password.twig', 'user/lost-password');
    }

    /**
     * Signing out, as a route rather than as `?SomePage&action=logout&context=SomePage`.
     *
     * That query string had to name the page it was written on, so it could only ever be
     * built by the action that rendered the link. A route is the same address everywhere.
     */
    #[Route('/user/logout', options: ['acl' => ['public']])]
    public function logout(): RedirectResponse
    {
        $urlFormatter = $this->getService(UrlFormatter::class);
        if (!$this->isConnected()) {
            return new RedirectResponse($urlFormatter->href('', 'user'));
        }

        $this->getService(AuthenticationService::class)->logout();
        $this->getService(FlashMessageService::class)->setMessage(_t('USER_YOU_ARE_NOW_DISCONNECTED') . ' !');

        return new RedirectResponse($urlFormatter->href());
    }

    /**
     * `getLoggedUser()`, NOT `getLoggedUserName()`: the latter answers with the client's IP
     * address when nobody is signed in -- an anonymous editor's "name" is their IP, by a
     * convention as old as the wiki -- so asking it whether there is a session gets "yes"
     * from every visitor, and every one of them was shown the account screen.
     */
    private function isConnected(): bool
    {
        return !empty($this->getService(AuthenticationService::class)->getLoggedUser());
    }

    private function toAccount(): RedirectResponse
    {
        return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'user'));
    }

    /**
     * An account screen inside the wiki's page skeleton -- or the sign-in form instead.
     *
     * @param array<string, mixed> $data
     */
    private function page(string $template, string $current, array $data = []): Response
    {
        // signed out, the rail has nothing of yours to point at, so no entry is current and
        // the sign-in form is what the canvas holds
        return $this->isConnected()
            ? $this->openPage($template, $current, $data)
            : $this->openPage('@core/account/login.twig', 'user', $data);
    }

    /**
     * A screen that renders for anyone -- signing in, signing up, recovering a password.
     *
     * These three are the whole reason the routes are public, and they must NOT go through
     * the gate above: replacing the signup form with the sign-in form for a visitor who has
     * no account yet is a closed door where the door is the point.
     *
     * @param array<string, mixed> $data
     */
    private function openPage(string $template, string $current, array $data = []): Response
    {
        // the whole path, not just the first segment: the URL parser reads `?user/pages` as
        // tag `user` + method `pages`, and an action that links to "this page" asks
        // PageContext for the tag (see DashboardController::page())
        $this->getService(PageContext::class)->setTag($current);

        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render($template, $this->dashboardShell($current, $data))));
    }
}
