<?php

namespace YesWiki\Test\Render;

use YesWiki\Render\Service\LayoutService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The login, sign-up and lost-password cards carry the wiki's logo and name (end-users 18). */
class GuestCardLogoTest extends YesWikiTestCase
{
    private function logoUrl(string $logo): string
    {
        $services = $this->getWiki()->services;
        $chrome = $services->get(LayoutService::class)->fromForm(['logo' => $logo, 'brand' => 'logo'], [], []);

        return $services->get(TemplateEngine::class)->brandLogoUrl($chrome);
    }

    public function testAFullUrlIsKeptAsItIs(): void
    {
        $this->assertSame('https://example.org/logo.png', $this->logoUrl('https://example.org/logo.png'));
    }

    public function testAStoragePathBecomesItsUrl(): void
    {
        $url = $this->logoUrl('files/logo.png');

        $this->assertNotSame('files/logo.png', $url);
        $this->assertStringContainsString('logo.png', $url);
    }

    public function testNoLogoGivesNoUrl(): void
    {
        $this->assertSame('', $this->logoUrl(''));
    }

    public function testTheLoginHeadingNamesTheWiki(): void
    {
        $services = $this->getWiki()->services;
        $title = $services->get(LayoutService::class)->current()->title;
        $rendered = $services->get(TemplateEngine::class)->render('@core/account/login.twig', []);

        $this->assertStringContainsString('<h1>' . htmlspecialchars(_t('LOGIN_LOGIN_TO', ['wiki' => $title])) . '</h1>', $rendered);
    }
}
