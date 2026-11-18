<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use YesWiki\Identity\Service\SignIn\Type\LdapType;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Provider\LdapConnection\NativeLdapConnection;
use YesWiki\Identity\SignIn\Provider\LdapProvider;

require_once __DIR__ . '/LdapStubServer.php';

/** A sign-in through the LDAP provider and ext-ldap against LdapStubServer. */
#[RequiresPhpExtension('ldap')]
class LdapProviderOverTheWireTest extends TestCase
{
    private const PASSWORD = 'Sésame-0uvre-toi';
    private const PEOPLE = 'ou=people,dc=acme,dc=example';
    private const GROUPS = 'ou=groups,dc=acme,dc=example';
    private const MARIE_DN = 'uid=marie,' . self::PEOPLE;
    private const MARIE = [
        'objectClass' => ['inetOrgPerson'],
        'uid' => ['marie'],
        'cn' => ['Marie Dupont'],
        'givenName' => ['Marie'],
        'sn' => ['Dupont'],
        'mail' => ['marie.dupont@acme.example'],
        'userPassword' => [self::PASSWORD],
    ];

    private ?LdapStubServer $directory = null;

    protected function tearDown(): void
    {
        $this->directory?->stop();
    }

    public function testMarieSignsInWithHerAttributesAndGroups(): void
    {
        $provider = $this->provider([
            ['op' => 'bind', 'code' => 0],
            ['op' => 'search', 'code' => 0, 'entries' => [['dn' => self::MARIE_DN, 'attributes' => self::MARIE]]],
            ['op' => 'search', 'code' => 0, 'entries' => [
                ['dn' => 'cn=wiki-editors,' . self::GROUPS, 'attributes' => ['cn' => ['wiki-editors']]],
                ['dn' => 'cn=staff,' . self::GROUPS, 'attributes' => ['cn' => ['staff']]],
            ]],
        ]);

        $identity = $provider->authenticate(' marie ', self::PASSWORD);

        $this->assertSame([self::MARIE_DN], $identity->attributes['dn']);
        $this->assertSame(['marie'], $identity->attributes['uid']);
        $this->assertSame(['marie.dupont@acme.example'], $identity->attributes['mail']);
        $this->assertSame(['Marie'], $identity->attributes['givenName']);
        $this->assertSame(['Dupont'], $identity->attributes['sn']);
        $this->assertSame(['wiki-editors', 'staff'], $identity->attributes['groups']);
        $this->assertArrayNotHasKey('userPassword', $identity->attributes);

        $requests = $this->requests();
        $this->assertSame(['op' => 'bind', 'version' => 3, 'dn' => self::MARIE_DN, 'auth' => 'simple', 'password' => true], $requests[0], 'bound as her, through the template');
        $this->assertSame(['search', self::MARIE_DN, 0, '(objectClass=*)'], [$requests[1]['op'], $requests[1]['base'], $requests[1]['scope'], $requests[1]['filter']], 'her entry, read as her');
        $this->assertSame(['search', self::GROUPS, 2, '(member=' . self::MARIE_DN . ')'], [$requests[2]['op'], $requests[2]['base'], $requests[2]['scope'], $requests[2]['filter']], 'her groups, by the default filter');
        $this->assertCount(3, $requests);
    }

    public function testALoginWithFilterCharactersReachesTheDirectoryEscaped(): void
    {
        $provider = $this->provider([['op' => 'bind', 'code' => 0], ['op' => 'search', 'code' => 0, 'entries' => []]], [
            'bind_dn_template' => '',
            'base_dn' => self::PEOPLE,
            'group_base' => '',
        ]);

        $failure = $this->failure(fn () => $provider->authenticate('marie)(uid=*', self::PASSWORD));

        $this->assertSame(FailureReason::WrongCredentials, $failure->reason, 'nobody is found');
        $requests = $this->requests();
        $this->assertSame(['op' => 'bind', 'version' => 3, 'dn' => '', 'auth' => 'simple', 'password' => false], $requests[0], 'the search is anonymous');
        $this->assertSame('(uid=marie\\29\\28uid=\\2a)', $requests[1]['filter'], 'one equality, not a filter the login wrote');
    }

    public function testAWrongPasswordIsWrongCredentialsAndQuotesNothingOfIt(): void
    {
        $provider = $this->provider([['op' => 'bind', 'code' => 49, 'diagnostic' => 'invalid credentials for ' . self::MARIE_DN]]);

        $failure = $this->failure(fn () => $provider->authenticate('marie', self::PASSWORD));

        $this->assertSame(FailureReason::WrongCredentials, $failure->reason);
        $this->assertStringContainsString(ldap_err2str(49), $failure->adminDetail);
        $this->assertStringContainsString('(49)', $failure->adminDetail);
        $this->assertStringNotContainsString(self::PASSWORD, $failure->adminDetail . $failure->getMessage());
        $this->assertNotNull($this->directory);
        $this->assertStringNotContainsString(self::PASSWORD, $this->directory->log(), 'nor did the wire carry it anywhere the stub logs');
    }

    public function testADirectoryThatIsDownIsUnreachable(): void
    {
        $provider = new LdapProvider($this->config('ldap://127.0.0.1:' . LdapStubServer::closedPort()), new NativeLdapConnection());

        $failure = $this->failure(fn () => $provider->authenticate('marie', self::PASSWORD));

        $this->assertSame(FailureReason::ProviderUnreachable, $failure->reason);
        $this->assertStringContainsString(ldap_err2str(-1), $failure->adminDetail);
    }

    public function testAStartTlsTheDirectoryRefusesIsUnreachable(): void
    {
        $provider = $this->provider([['op' => 'starttls', 'code' => 52]], ['start_tls' => true]);

        $failure = $this->failure(fn () => $provider->authenticate('marie', self::PASSWORD));

        $this->assertSame(FailureReason::ProviderUnreachable, $failure->reason);
        $this->assertStringContainsString('StartTLS failed', $failure->adminDetail);
        $this->assertCount(1, $this->requests(), 'not a bind after it');
    }

    /**
     * The provider of the guides' company directory, bound by a DN template, groups searched under their base.
     *
     * @param list<array<string, mixed>> $replies
     * @param array<string, mixed>       $connection merged over it
     */
    private function provider(array $replies, array $connection = []): LdapProvider
    {
        $this->directory = new LdapStubServer($replies);

        return new LdapProvider($this->config($this->directory->uri(), $connection), new NativeLdapConnection());
    }

    /** @param array<string, mixed> $connection */
    private function config(string $uri, array $connection = []): ProviderConfig
    {
        return ProviderConfig::fromArray('ldap', [
            'type' => 'ldap',
            'label' => 'Acme directory',
            'connection' => array_merge(['uri' => $uri, 'bind_dn_template' => 'uid={login},' . self::PEOPLE, 'group_base' => self::GROUPS], $connection),
            'attributes' => ['id' => 'uid', 'email' => 'mail', 'groups' => 'groups'],
        ], (new LdapType())->defaultAttributes());
    }

    /** @return list<array<string, mixed>> */
    private function requests(): array
    {
        $this->assertNotNull($this->directory);

        return $this->directory->requests();
    }

    private function failure(callable $sign): ProviderFailure
    {
        try {
            $sign();
        } catch (ProviderFailure $failure) {
            return $failure;
        }
        $this->fail('no failure');
    }
}
