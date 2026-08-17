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

/** `/user` -- your account, and the way in when you have none open. */
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

    /** Creating an account -- the other half of the sign-in screen. */
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

    /** Signing out, as a route rather than as `?SomePage&action=logout&context=SomePage`. */
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
     * `getLoggedUser()`, NOT `getLoggedUserName()`: the latter answers with the client's IP address when nobody is signed in -- an anonymous editor's "name" is their IP, by a convention as old as the wiki -- so asking it whether there is a session gets "yes" from every visitor, and every one of them was shown the account screen.
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
        return $this->isConnected()
            ? $this->openPage($template, $current, $data)
            : $this->openPage('@core/account/login.twig', 'user', $data);
    }

    /**
     * A screen that renders for anyone -- signing in, signing up, recovering a password.
     *
     * @param array<string, mixed> $data
     */
    private function openPage(string $template, string $current, array $data = []): Response
    {
        $this->getService(PageContext::class)->setTag($current);

        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render($template, $this->dashboardShell($current, $data))));
    }
}
