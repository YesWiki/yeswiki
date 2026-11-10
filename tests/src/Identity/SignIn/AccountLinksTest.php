<?php

namespace YesWiki\Test\Identity\SignIn;

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

    private $services;
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
        return $this->users->create($tag, $email, 'Aa1!aaaaRegression');
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
        $this->assertSame('', $this->users->getOneByName('SsoLinkB')->getPassword());
        $this->assertSame('acme', $this->users->getOneByName('SsoLinkB')->provider());
    }

    public function testLinkIsSeenByTheNextReadOfTheSameRequest(): void
    {
        $user = $this->makeUser('SsoLinkB', 'ssolinkb@example.com');
        $pages = $this->services->get(PageManager::class);
        // warm both per-request caches before the write
        $pages->getOne('SsoLinkB', null, true, true);
        $pages->getOne('SsoLinkB');
        $this->links->link($user, 'acme', 'sub-1');
        $this->assertSame('sub-1', $pages->getOne('SsoLinkB', null, true, true)['body']['sso_subject']);
        $this->assertSame('sub-1', $this->links->subjectOf($this->users->getOneByName('SsoLinkB')));
        $this->links->link($this->users->getOneByName('SsoLinkB'), 'acme', 'sub-2');
        $this->assertSame('sub-2', $this->links->subjectOf($this->users->getOneByName('SsoLinkB')));
    }

    public function testUserUpdateCannotSetTheKeys(): void
    {
        $user = $this->makeUser('SsoLinkC', 'ssolinkc@example.com');
        $this->users->update($user, ['sso_provider' => 'evil', 'sso_subject' => 'x', 'motto' => 'm']);
        $this->assertNull($this->users->getOneByName('SsoLinkC')->provider());
        $this->assertSame('m', $this->users->getOneByName('SsoLinkC')['motto']);
    }

    public function testAnyPageSaveKeepsTheStoredKeys(): void
    {
        $user = $this->makeUser('SsoLinkD', 'ssolinkd@example.com');
        $this->links->link($user, 'acme', 'sub-1');
        $pages = $this->services->get(PageManager::class);
        $body = $pages->getOne('SsoLinkD', null, true, true)['body'];
        $body['sso_subject'] = 'stolen';
        unset($body['sso_provider']);
        $pages->save('SsoLinkD', $body, '', true);
        $this->assertSame('sub-1', $this->links->subjectOf($this->users->getOneByName('SsoLinkD')));
        $this->assertSame('acme', $this->users->getOneByName('SsoLinkD')->provider());
    }

    public function testAPageSaveCannotLinkALocalAccount(): void
    {
        $this->makeUser('SsoLinkE', 'ssolinke@example.com');
        $pages = $this->services->get(PageManager::class);
        $body = $pages->getOne('SsoLinkE', null, true, true)['body'];
        $body['sso_provider'] = 'acme';
        $pages->save('SsoLinkE', $body, '', true);
        $this->assertNull($this->users->getOneByName('SsoLinkE')->provider());
    }

    public function testTheKeysAreHiddenFromGenericReads(): void
    {
        // through Guard, the path every generic page read uses
        $user = $this->makeUser('SsoLinkF', 'ssolinkf@example.com');
        $this->links->link($user, 'acme', 'sub-1');
        self::actAs(null);
        $page = $this->services->get(PageManager::class)->getOne('SsoLinkF');
        $this->assertArrayNotHasKey('sso_provider', $page['body']);
        $this->assertArrayNotHasKey('sso_subject', $page['body']);
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
