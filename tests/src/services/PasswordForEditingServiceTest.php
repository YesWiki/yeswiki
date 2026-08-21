<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Identity\Service\PasswordForEditingService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\ForcedParameterBag;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/ForcedParameterBag.php';

/**
 * Regression tests for ticket 15 (security-core-split): PasswordForEditingService is the new, standalone home for the shared-editing-password gate previously bundled inside SecurityController (renamed to InputFilter by wave-two ticket 03).
 */
#[CoversMethod(PasswordForEditingService::class, 'isGrantedPasswordForEditing')]
class PasswordForEditingServiceTest extends YesWikiTestCase
{
    private function buildService(YesWikiRuntime $wiki, string $password): PasswordForEditingService
    {
        $realParams = $wiki->services->get(ParameterBagInterface::class);
        $forcedParams = new ForcedParameterBag($realParams, ['password_for_editing' => $password]);

        return new PasswordForEditingService($wiki->services, $forcedParams, $wiki->services->get(TemplateEngine::class));
    }

    public function testNotActivatedWhenPasswordForEditingIsEmpty(): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->remove('password_for_editing');

        [$state, $message] = $this->buildService($wiki, '')->isGrantedPasswordForEditing();

        $this->assertTrue($state);
        $this->assertSame('', $message);
    }

    public function testDeniedWithoutRequestPasswordThenGrantedWithCorrectOne(): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->remove('password_for_editing');
        $service = $this->buildService($wiki, 'sesame');

        [$state, $message] = $service->isGrantedPasswordForEditing();
        $this->assertFalse($state);
        $this->assertNotEmpty($message);

        try {
            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->set('password_for_editing', 'wrong');
            [$state] = $service->isGrantedPasswordForEditing();
            $this->assertFalse($state);

            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->set('password_for_editing', 'sesame');
            [$state, $message] = $service->isGrantedPasswordForEditing();
            $this->assertTrue($state);
            $this->assertSame('', $message);
        } finally {
            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->remove('password_for_editing');
        }
    }

    public function testAlreadyLoggedInUserBypassesPasswordGate(): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get()->request->remove('password_for_editing');
        $userManager = $wiki->services->get(UserManager::class);
        $authenticationService = $wiki->services->get(\YesWiki\Identity\Service\AuthenticationService::class);

        do {
            $name = trim(StringUtilService::generateRandomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                . StringUtilService::generateRandomString(15, 'abcdefghijklmnopqrstuvwxyz0123456789'));
        } while (!empty($userManager->getOneByName($name)));
        $userManager->create($name, strtolower($name) . '@example.com', 'irrelevant-password-1234');
        $user = self::requireUser($userManager->getOneByName($name));

        try {
            $authenticationService->login($user);

            [$state, $message] = $this->buildService($wiki, 'sesame')->isGrantedPasswordForEditing();

            $this->assertTrue($state, 'a logged-in user must bypass the shared editing password');
            $this->assertSame('', $message);
        } finally {
            $authenticationService->logout();
            $userManager->delete($user);
        }
    }
}
