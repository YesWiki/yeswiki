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

    /** The fixture names, and the pages that would hold them: a test must not find another's. */
    private const NAMES = ['MarieDupont', 'MarieDupontLefevre', 'MarieDupontLefevre2', 'jdupont', 'jdupont-2'];

    private function cleanUp(): void
    {
        foreach (self::NAMES as $name) {
            $user = $this->users->getOneByName($name);
            if ($user) {
                $this->users->delete($user);
            }
            $this->services->get(PageManager::class)->deleteOrphaned($name);
        }
    }

    /** @param array<string, mixed> $settings */
    private function config(array $settings = []): ProviderConfig
    {
        return ProviderConfig::fromArray('acme', array_merge(['type' => 'oidc'], $settings), [
            'id' => 'sub', 'email' => 'email', 'groups' => 'groups',
        ]);
    }

    /** loginsso's template, made into a WikiName as it made it. */
    public function testTheTemplateNamesTheAccountAsAWikiName(): void
    {
        $config = $this->config(['account_name_template' => '#[given_name] #[family_name]']);

        $this->assertSame('MarieDupontLefevre', $this->namer->suggest($config, ['given_name' => 'Marie', 'family_name' => 'Dupont-Lefèvre', 'email' => 'x@y', 'sub' => 's']));
        $this->assertSame('MdupontAcme', $this->namer->suggest($this->config(['account_name_template' => '#[uid] Acme']), ['uid' => 'mdupont', 'sub' => 's']), 'attributes and text alike');
    }

    /** @return array<string, array{string, string}> */
    public static function wikiNames(): array
    {
        return [
            'words' => ['Marie Dupont', 'MarieDupont'],
            'punctuation splits words' => ['marie.dupont', 'MarieDupont'],
            'accents go' => ['Jean-Édouard d’Ormesson', 'JeanEdouardDOrmesson'],
            'one capital gets a second' => ['jdupont', 'JduponT'],
            'digits stay' => ['S 42', 'S42'],
            'nothing usable' => ['🎉', ''],
            'cut to 47 characters' => [str_repeat('a', 60), 'A' . str_repeat('a', 45) . 'A'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('wikiNames')]
    public function testAWikiNameIsMadeAsLoginssoMadeIt(string $text, string $wikiName): void
    {
        $this->assertSame($wikiName, AccountNamer::wikiName($text));
    }

    /** @return array<string, array{string, string}> */
    public static function namesAsSent(): array
    {
        return [
            'a uid stays as it is' => ['jdupont', 'jdupont'],
            'case and accents stay, spaces become dashes' => ['Marie Dupont-Lefèvre', 'Marie-Dupont-Lefèvre'],
            'the dots of an email stay' => ['marie.dupont', 'marie.dupont'],
            'what a page name cannot hold goes' => ['a/b@c d²', 'abc-d'],
            'nothing usable' => ['🎉', ''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('namesAsSent')]
    public function testWithoutPascalCaseTheNameIsKeptAsSent(string $text, string $name): void
    {
        $this->assertSame($name, AccountNamer::asSent($text));
    }

    /** loginldap and logincas named an account after the uid or `name` as sent: numbered here, where they took the account. */
    public function testWithoutPascalCaseTheTemplateGivesTheNameAsSentNumberedWhenTaken(): void
    {
        $config = $this->config(['account_name_template' => '#[uid]', 'account_name_pascalcase' => false]);
        $this->assertSame('jdupont', $this->namer->suggest($config, ['uid' => 'jdupont', 'sub' => 's']));

        $this->users->create('jdupont', 'jd@x', 'Aa1!aaaaRegression');
        $this->assertSame('jdupont-2', $this->namer->suggest($config, ['uid' => 'jdupont', 'sub' => 's']));
        $this->assertSame('marie.dupont', $this->namer->suggest($this->config(['account_name_pascalcase' => false]), ['email' => 'marie.dupont@x', 'sub' => 's']), 'the email as sent too');
    }

    public function testPascalCaseIsOnUnlessSaidOtherwise(): void
    {
        $this->assertTrue($this->config()->accountNamePascalCase);
        $this->assertFalse($this->config(['account_name_pascalcase' => 'false'])->accountNamePascalCase, 'written as text, as the screen reads it');
    }

    public function testWithoutATemplateTheNameComesFromTheStartOfTheEmail(): void
    {
        $this->assertSame('MarieDupont', $this->namer->suggest($this->config(), ['given_name' => 'Ann', 'email' => 'marie.dupont@y', 'sub' => 's']));
    }

    public function testFallsBackWhenTheTemplateYieldsNothing(): void
    {
        $this->assertSame('JdoE', $this->namer->suggest($this->config(['account_name_template' => '#[given_name]']), ['given_name' => '🎉', 'email' => 'jdoe@x', 'sub' => 's']));
        $this->assertSame('JdoE', $this->namer->suggest($this->config(['account_name_template' => '#[missing]']), ['email' => 'jdoe@x', 'sub' => 's']));
    }

    public function testFallsBackToTheUserIdWhenThereIsNothingElse(): void
    {
        $this->assertSame('S42', $this->namer->suggest($this->config(), ['sub' => 'S 42']));
    }

    public function testAReservedNameFallsThroughToTheNextCandidate(): void
    {
        $this->assertSame('MariE', $this->namer->suggest($this->config(), ['email' => 'admin@x', 'sub' => 'marie']));
        $this->assertSame('MariE', $this->namer->suggest($this->config(['account_name_template' => '#[uid]']), ['uid' => 'user', 'email' => 'marie@x', 'sub' => 's']));
    }

    public function testNeverReturnsAReservedOrTakenName(): void
    {
        $name = $this->namer->suggest($this->config(), ['email' => 'admin@x', 'sub' => 'user']);
        $this->assertFalse(ReservedTags::isReserved($name));

        $config = $this->config(['account_name_template' => '#[given_name] #[family_name]']);
        $this->users->create('MarieDupontLefevre', 'md@x', 'Aa1!aaaaRegression');
        $this->assertSame('MarieDupontLefevre2', $this->namer->suggest($config, ['given_name' => 'Marie', 'family_name' => 'Dupont-Lefèvre', 'sub' => 's']), 'numbered as loginsso numbered it');
    }
}
