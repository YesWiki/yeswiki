<?php

namespace YesWiki\Test\Identity;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Login dropdown button class. */
class LoginButtonClassTest extends YesWikiTestCase
{
    private const USER = 'LoginButtonClassUser';

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->logout();
        $userManager = $services->get(UserManager::class);
        if ($user = $userManager->getOneByName(self::USER)) {
            $userManager->delete($user);
        }
    }

    /** @return array<string, array{bool}> */
    public static function states(): array
    {
        return ['logged out' => [false], 'logged in' => [true]];
    }

    #[DataProvider('states')]
    public function testAGivenClassIsOnTheToggle(bool $loggedIn): void
    {
        $classes = $this->toggleClasses($loggedIn, 'yw-btn--primary');

        $this->assertStringContainsString('yw-btn--primary', $classes);
        $this->assertStringNotContainsString('btn-default', $classes);
    }

    #[DataProvider('states')]
    public function testWithoutAClassTheToggleIsAPlainButton(bool $loggedIn): void
    {
        $this->assertSame('yw-btn', trim($this->toggleClasses($loggedIn, '')));
    }

    private function toggleClasses(bool $loggedIn, string $btnclass): string
    {
        $services = $this->getWiki()->services;
        $authentication = $services->get(AuthenticationService::class);
        $authentication->logout();
        if ($loggedIn) {
            $userManager = $services->get(UserManager::class);
            if (!$userManager->getOneByName(self::USER)) {
                $userManager->create(self::USER, 'login-button-class@example.tld', 'Aa1!aaaaLoginButton');
            }
            $authentication->login(self::requireUser($userManager->getOneByName(self::USER)));
        }

        $html = (string)$services->get(MarkdownFormatterService::class)->format(
            '{{login template="dropdown.twig"' . ($btnclass === '' ? '' : ' btnclass="' . $btnclass . '"') . '}}'
        );

        $this->assertStringContainsString('class="yw-dropdown"', $html);
        $this->assertStringNotContainsString('data-toggle="dropdown"', $html);
        if (preg_match('/<button type="button" class="([^"]*)" data-yw-dropdown-toggle/', $html, $matches) !== 1) {
            $this->fail('the login dropdown has no toggle button');
        }

        return $matches[1];
    }
}
