<?php

namespace YesWiki\Test\Login\Action;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The login button takes the class it is given instead of always being btn-default.
 */
class LoginButtonClassTest extends YesWikiTestCase
{
    private const USER = 'LoginButtonClassUser';

    private $wiki;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->wiki->services->get(AuthController::class)->logout();
    }

    protected function tearDown(): void
    {
        $this->wiki->services->get(AuthController::class)->logout();
        $userManager = $this->wiki->services->get(UserManager::class);
        if ($user = $userManager->getOneByName(self::USER)) {
            $userManager->delete($user);
        }
    }

    private function logIn(): void
    {
        $userManager = $this->wiki->services->get(UserManager::class);
        $userManager->create(self::USER, 'login-button-class@example.org', 'login-button-class-password');
        $this->wiki->services->get(AuthController::class)->login($userManager->getOneByName(self::USER));
    }

    private function buttonClasses(string $template, string $btnclass): string
    {
        $html = $this->wiki->Format('{{login template="' . $template . '"' . ($btnclass === '' ? '' : ' btnclass="' . $btnclass . '"') . '}}');
        $this->assertMatchesRegularExpression('/<(?:a href="#LoginModal"|button data-toggle="dropdown")[^>]*class="([^"]*)"/', $html);
        preg_match('/<(?:a href="#LoginModal"|button data-toggle="dropdown")[^>]*class="([^"]*)"/', $html, $matches);

        return $matches[1];
    }

    public static function cases(): array
    {
        return [
            'modal, logged out' => ['modal.twig', false],
            'modal, logged in' => ['modal.twig', true],
            'dropdown, logged out' => ['dropdown.twig', false],
            'dropdown, logged in' => ['dropdown.twig', true],
        ];
    }

    #[DataProvider('cases')]
    public function testAGivenClassReplacesBtnDefault(string $template, bool $loggedIn)
    {
        if ($loggedIn) {
            $this->logIn();
        }
        $classes = $this->buttonClasses($template, 'btn-secondary-1');

        $this->assertStringContainsString('btn-secondary-1', $classes);
        $this->assertStringNotContainsString('btn-default', $classes);
    }

    #[DataProvider('cases')]
    public function testWithoutAClassTheButtonStaysBtnDefault(string $template, bool $loggedIn)
    {
        if ($loggedIn) {
            $this->logIn();
        }

        $this->assertStringContainsString('btn-default', $this->buttonClasses($template, ''));
    }

    public function testTheLoggedOutModalButtonHasATitle()
    {
        $html = $this->wiki->Format('{{login template="modal.twig"}}');

        $this->assertMatchesRegularExpression('/<a href="#LoginModal"[^>]*title="' . preg_quote(_t('LOGIN_LOGIN'), '/') . '"/', $html);
        $this->assertStringNotContainsString("_t('LOGIN_LOGIN')", $html);
    }
}
