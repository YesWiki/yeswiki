<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\DependencyInjection\ContainerInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class AccountLinksTest extends YesWikiTestCase
{
    private const TAGS = ['SsoLinkA', 'SsoLinkB', 'SsoLinkC', 'SsoLinkD', 'SsoLinkE', 'SsoLinkF', 'SsoLinkG', 'SsoLinkH'];

    private ContainerInterface $services;
    private UserManager $users;
    private AccountLinks $links;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getWiki()->services;
        $this->users = $this->services->get(UserManager::class);
        $this->links = $this->services->get(AccountLinks::class);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        self::actAs(null);
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        foreach (self::TAGS as $tag) {
            $user = $this->users->getOneByName($tag);
            if ($user) {
                $this->users->delete($user);
            }
            $this->services->get(PageManager::class)->deleteOrphaned($tag);
        }
    }

    private function makeUser(string $tag, string $email): User
    {
        $user = $this->users->create($tag, $email, 'Aa1!aaaaRegression');
        $this->assertNotNull($user, "$tag was created");

        return $user;
    }

    private function account(string $tag): User
    {
        $user = $this->users->getOneByName($tag);
        $this->assertNotNull($user, "$tag exists");

        return $user;
    }

    /**
     * @param ?array<string, mixed> $page
     *
     * @return array<array-key, mixed>
     */
    private function bodyOf(?array $page): array
    {
        $this->assertNotNull($page);
        $body = $page['body'] ?? null;
        $this->assertIsArray($body);

        return $body;
    }

    public function testFindsTheAccountByProviderAndSubject(): void
    {
        $user = $this->makeUser('SsoLinkA', 'ssolinka@example.com');
        $this->links->link($user, 'acme', 'sub-1');
        $this->assertSame('SsoLinkA', $this->links->find('acme', 'sub-1')?->getName());
        $this->assertNull($this->links->find('other', 'sub-1'));
        $this->assertNull($this->links->find('acme', 'sub-2'));
    }

    public function testFindsTheAccountByEmailIgnoringCase(): void
    {
        $this->makeUser('SsoLinkA', 'ssolinka@example.com');
        $this->assertSame('SsoLinkA', $this->links->findByEmail('SsoLinkA@Example.COM')?->getName());
        $this->assertNull($this->links->findByEmail('nobody@example.com'));
    }

    public function testLinkingClearsThePassword(): void
    {
        $user = $this->makeUser('SsoLinkB', 'ssolinkb@example.com');
        $this->links->link($user, 'acme', 'sub-1');
        $this->assertSame('', $this->account('SsoLinkB')->getPassword());
        $this->assertSame('acme', $this->account('SsoLinkB')->provider());
    }

    public function testLinkIsSeenByTheNextReadOfTheSameRequest(): void
    {
        $user = $this->makeUser('SsoLinkB', 'ssolinkb@example.com');
        $pages = $this->services->get(PageManager::class);
        // warm both per-request caches before the write
        $pages->getOne('SsoLinkB', null, true, true);
        $pages->getOne('SsoLinkB');
        $this->links->link($user, 'acme', 'sub-1');
        $this->assertSame('sub-1', $this->bodyOf($pages->getOne('SsoLinkB', null, true, true))['sso_subject']);
        $this->assertSame('sub-1', $this->links->subjectOf($this->account('SsoLinkB')));
        $this->links->link($this->account('SsoLinkB'), 'acme', 'sub-2');
        $this->assertSame('sub-2', $this->links->subjectOf($this->account('SsoLinkB')));
    }

    public function testUserUpdateCannotSetTheKeys(): void
    {
        $user = $this->makeUser('SsoLinkC', 'ssolinkc@example.com');
        $this->users->update($user, ['sso_provider' => 'evil', 'sso_subject' => 'x', 'motto' => 'm']);
        $this->assertNull($this->account('SsoLinkC')->provider());
        $this->assertSame('m', $this->account('SsoLinkC')['motto']);
    }

    public function testAnyPageSaveKeepsTheStoredKeys(): void
    {
        $user = $this->makeUser('SsoLinkD', 'ssolinkd@example.com');
        $this->links->link($user, 'acme', 'sub-1');
        $pages = $this->services->get(PageManager::class);
        $body = $this->bodyOf($pages->getOne('SsoLinkD', null, true, true));
        $body['sso_subject'] = 'stolen';
        unset($body['sso_provider']);
        $pages->save('SsoLinkD', $body, '', true);
        $this->assertSame('sub-1', $this->links->subjectOf($this->account('SsoLinkD')));
        $this->assertSame('acme', $this->account('SsoLinkD')->provider());
    }

    public function testAPageSaveCannotLinkALocalAccount(): void
    {
        $this->makeUser('SsoLinkE', 'ssolinke@example.com');
        $pages = $this->services->get(PageManager::class);
        $body = $this->bodyOf($pages->getOne('SsoLinkE', null, true, true));
        $body['sso_provider'] = 'acme';
        $pages->save('SsoLinkE', $body, '', true);
        $this->assertNull($this->account('SsoLinkE')->provider());
    }

    public function testTheKeysAreHiddenFromGenericReads(): void
    {
        // through Guard, the path every generic page read uses
        $user = $this->makeUser('SsoLinkF', 'ssolinkf@example.com');
        $this->links->link($user, 'acme', 'sub-1');
        self::actAs(null);
        $page = $this->services->get(PageManager::class)->getOne('SsoLinkF');
        $this->assertArrayNotHasKey('sso_provider', $this->bodyOf($page));
        $this->assertArrayNotHasKey('sso_subject', $this->bodyOf($page));
    }

    public function testFirstAndLastNameAreLockedFieldsOfTheUserForm(): void
    {
        $this->assertTrue(ContentTypeSchema::isLocked('user', 'first_name'));
        $this->assertTrue(ContentTypeSchema::isLocked('user', 'last_name'));
        $this->assertSame('texte', ContentTypeSchema::lockedFieldType('user', 'first_name'));
    }

    public function testCreateLinkedMakesAnAccountWithNoPassword(): void
    {
        $user = $this->users->createLinked('SsoLinkG', 'ssolinkg@example.com', ['first_name' => 'A', 'last_name' => 'B'], 'acme', 'sub-9');
        $this->assertNotNull($user);
        $this->assertSame('', $user->getPassword());
        $this->assertSame('acme', $user->provider());
        $this->assertSame('sub-9', $this->links->subjectOf($user));
        $this->assertSame('SsoLinkG', $this->links->find('acme', 'sub-9')?->getName());
        $this->assertFalse($this->services->get(AuthenticationService::class)->checkPassword('', $user));
    }

    public function testCountsByProvider(): void
    {
        $this->links->link($this->makeUser('SsoLinkH', 'h@example.com'), 'countme', 's1');
        $this->assertSame(1, $this->links->countByProvider()['countme']);
    }
}
