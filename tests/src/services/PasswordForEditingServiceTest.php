<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\PasswordForEditingService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 15 (security-core-split): PasswordForEditingService is the
 * new, standalone home for the shared-editing-password gate previously bundled inside
 * SecurityController.
 */
#[CoversMethod(PasswordForEditingService::class, 'isGrantedPasswordForEditing')]
class PasswordForEditingServiceTest extends YesWikiTestCase
{
    private function buildService(Wiki $wiki, string $password): PasswordForEditingService
    {
        $realParams = $wiki->services->get(ParameterBagInterface::class);
        $forcedParams = new class ($realParams, $password) implements ParameterBagInterface {
            public function __construct(private ParameterBagInterface $real, private string $password)
            {
            }

            public function get(string $name): \UnitEnum|array|string|int|float|bool|null
            {
                return $name === 'password_for_editing' ? $this->password : $this->real->get($name);
            }

            public function has(string $name): bool
            {
                return $name === 'password_for_editing' ? true : $this->real->has($name);
            }

            public function clear(): void
            {
                $this->real->clear();
            }
            public function add(array $parameters): void
            {
                $this->real->add($parameters);
            }
            public function all(): array
            {
                return $this->real->all();
            }
            public function remove(string $name): void
            {
                $this->real->remove($name);
            }
            public function set(string $name, $value): void
            {
                $this->real->set($name, $value);
            }
            public function resolve(): void
            {
                $this->real->resolve();
            }
            public function resolveValue(mixed $value): mixed
            {
                return $this->real->resolveValue($value);
            }
            public function escapeValue(mixed $value): mixed
            {
                return $this->real->escapeValue($value);
            }
            public function unescapeValue(mixed $value): mixed
            {
                return $this->real->unescapeValue($value);
            }
        };

        return new PasswordForEditingService($wiki, $forcedParams, $wiki->services->get(TemplateEngine::class));
    }

    public function testNotActivatedWhenPasswordForEditingIsEmpty()
    {
        $wiki = $this->getWiki();
        $wiki->request->request->remove('password_for_editing');

        [$state, $message] = $this->buildService($wiki, '')->isGrantedPasswordForEditing();

        $this->assertTrue($state);
        $this->assertSame('', $message);
    }

    public function testDeniedWithoutRequestPasswordThenGrantedWithCorrectOne()
    {
        $wiki = $this->getWiki();
        $wiki->request->request->remove('password_for_editing');
        $service = $this->buildService($wiki, 'sesame');

        [$state, $message] = $service->isGrantedPasswordForEditing();
        $this->assertFalse($state);
        $this->assertNotEmpty($message);

        try {
            $wiki->request->request->set('password_for_editing', 'wrong');
            [$state] = $service->isGrantedPasswordForEditing();
            $this->assertFalse($state);

            $wiki->request->request->set('password_for_editing', 'sesame');
            [$state, $message] = $service->isGrantedPasswordForEditing();
            $this->assertTrue($state);
            $this->assertSame('', $message);
        } finally {
            $wiki->request->request->remove('password_for_editing');
        }
    }

    public function testAlreadyLoggedInUserBypassesPasswordGate()
    {
        $wiki = $this->getWiki();
        $wiki->request->request->remove('password_for_editing');
        $userManager = $wiki->services->get(UserManager::class);
        $authController = $wiki->services->get(\YesWiki\Core\Controller\AuthController::class);

        do {
            $name = trim($wiki->generateRandomString(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')
                . $wiki->generateRandomString(15, 'abcdefghijklmnopqrstuvwxyz0123456789'));
        } while (!empty($userManager->getOneByName($name)));
        $userManager->create($name, strtolower($name) . '@example.com', 'irrelevant-password-1234');
        $user = $userManager->getOneByName($name);

        try {
            $authController->login($user);

            [$state, $message] = $this->buildService($wiki, 'sesame')->isGrantedPasswordForEditing();

            $this->assertTrue($state, 'a logged-in user must bypass the shared editing password');
            $this->assertSame('', $message);
        } finally {
            $authController->logout();
            $userManager->delete($user);
        }
    }
}
