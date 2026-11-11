<?php

namespace YesWiki\Test\Identity\SignIn;

use Symfony\Component\DependencyInjection\ContainerInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\SignIn\AccountNamer;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

class AccountNamerTest extends YesWikiTestCase
{
    private ContainerInterface $services;
    private UserManager $users;
    private AccountNamer $namer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getWiki()->services;
        $this->users = $this->services->get(UserManager::class);
        $this->namer = $this->services->get(AccountNamer::class);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $user = $this->users->getOneByName('marie-dupont');
        if ($user) {
            $this->users->delete($user);
        }
        $this->services->get(PageManager::class)->deleteOrphaned('marie-dupont');
    }

    /** @param array<string, mixed> $settings */
    private function config(array $settings = []): ProviderConfig
    {
        return ProviderConfig::fromArray('acme', array_merge(['type' => 'oidc'], $settings), [
            'id' => 'sub', 'email' => 'email', 'first_name' => 'given_name', 'last_name' => 'family_name', 'groups' => 'groups',
        ]);
    }

    public function testNamesFromFirstAndLastName(): void
    {
        $this->assertSame('marie-dupont', $this->namer->suggest($this->config(), ['given_name' => 'Marie', 'family_name' => 'Dupont', 'email' => 'x@y', 'sub' => 's']));
    }

    public function testTemplateIsUsedWhenPresent(): void
    {
        $this->assertSame('mdupont-acme', $this->namer->suggest($this->config(['account_name_template' => '#[uid] Acme']), ['uid' => 'mdupont', 'sub' => 's']));
    }

    public function testFallsBackWhenTheNameYieldsNothing(): void
    {
        $this->assertSame('jdoe', $this->namer->suggest($this->config(), ['given_name' => '🎉', 'family_name' => '', 'email' => 'jdoe@x', 'sub' => 's']));
    }

    public function testFallsBackToTheUserIdWhenThereIsNothingElse(): void
    {
        $this->assertSame('s-42', $this->namer->suggest($this->config(), ['sub' => 'S 42']));
    }

    public function testAReservedNameFallsThroughToTheNextCandidate(): void
    {
        $this->assertSame('marie', $this->namer->suggest($this->config(), ['given_name' => 'Admin', 'email' => 'marie@x', 'sub' => 's']));
        $this->assertSame('marie', $this->namer->suggest($this->config(), ['given_name' => 'user', 'email' => 'marie@x', 'sub' => 's']));
    }

    public function testNeverReturnsAReservedOrTakenName(): void
    {
        $name = $this->namer->suggest($this->config(), ['given_name' => 'Admin', 'family_name' => '', 'email' => 'admin@x', 'sub' => 's']);
        $this->assertFalse(ReservedTags::isReserved($name));
        $this->users->create('marie-dupont', 'md@x', 'Aa1!aaaaRegression');
        $this->assertSame('marie-dupont-2', $this->namer->suggest($this->config(), ['given_name' => 'Marie', 'family_name' => 'Dupont', 'sub' => 's']));
    }
}
