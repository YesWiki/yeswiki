<?php

namespace YesWiki\Test\Identity;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Action\LostPasswordAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A password reset stores the password that was typed, not an HTML-escaped version of it. */
class LostPasswordKeepsThePasswordTypedTest extends YesWikiTestCase
{
    private const NAME = 'LostPasswordProbeAccount';

    protected function tearDown(): void
    {
        $wiki = $this->getWiki();
        $users = $wiki->services->get(UserManager::class);
        if ($user = $users->getOneByName(self::NAME)) {
            $users->delete($user);
        }
        $wiki->services->get(TripleStore::class)->delete(self::NAME, UserManager::KEY_VOCABULARY, null, '', '');
        $wiki->services->get(\YesWiki\Content\Service\PageManager::class)->deleteOrphaned(self::NAME);
        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function passwordsWithCharactersHtmlEscapes(): array
    {
        return [
            'ampersand' => ['Aa1!salt&pepper'],
            'double quote' => ['Aa1!say"what'],
            'single quote' => ['Aa1!it\'sfine'],
            'angle brackets' => ['Aa1!a<b>c'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('passwordsWithCharactersHtmlEscapes')]
    public function testAPasswordSurvivesTheResetItWasTypedInto(string $typed): void
    {
        $wiki = $this->getWiki();
        $services = $wiki->services;
        $users = $services->get(UserManager::class);

        $users->create(self::NAME, 'lostpasswordprobe@example.tld', 'Aa1!startingPassword');
        $user = $users->getOneByName(self::NAME);
        $this->assertNotNull($user, 'the probe account could not be created');

        $hashedKey = $services->get(PasswordHasherFactory::class)->getPasswordHasher($user)->hash('probe-key');
        $triples = $services->get(TripleStore::class);
        $triples->delete(self::NAME, UserManager::KEY_VOCABULARY, null, '', '');
        $triples->create(
            self::NAME,
            UserManager::KEY_VOCABULARY,
            $hashedKey . UserManager::KEY_VALUE_SEPARATOR . time(),
            '',
            ''
        );

        $this->submitReset($hashedKey, $typed);

        $reloaded = $services->get(UserManager::class)->getOneByName(self::NAME);
        $this->assertNotNull($reloaded);

        $auth = $services->get(AuthenticationService::class);
        $this->assertNotFalse(
            $auth->checkPassword($typed, $reloaded),
            'the password typed into the reset form must be the one that logs in'
        );
        $this->assertFalse(
            $auth->checkPassword(htmlspecialchars(strip_tags($typed)), $reloaded),
            'an HTML-escaped version of the password must not be what got stored'
        );
    }

    /** Drive subStep 2 the way the reset form posts it. */
    private function submitReset(string $key, string $password): void
    {
        $wiki = $this->getWiki();
        $services = $wiki->services;

        $request = Request::create('/?MotDePassePerdu', 'POST', [
            'subStep' => '2',
            'userID' => self::NAME,
            'key' => $key,
            'pw0' => $password,
            'pw1' => $password,
        ]);
        $services->get(CurrentRequest::class)->replace($request);

        $action = new LostPasswordAction();
        $action->setServices($services);

        $reflection = new \ReflectionClass($action);
        foreach ([
            'authenticationService' => AuthenticationService::class,
            'inputFilter' => InputFilter::class,
            'hibernationService' => HibernationService::class,
            'tripleStore' => TripleStore::class,
            'userManager' => UserManager::class,
        ] as $property => $service) {
            $reflection->getProperty($property)->setValue($action, $services->get($service));
        }

        $reflection->getMethod('manageSubStep')->invoke($action, 2);
    }
}
