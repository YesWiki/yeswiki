<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\TestCase;
use YesWiki\Identity\Service\SignIn\Type\LdapType;
use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;
use YesWiki\Identity\SignIn\Provider\LdapConnection;
use YesWiki\Identity\SignIn\Provider\LdapProvider;

/**
 * LDAP as a password provider (spec sections 6 and 12): a direct bind through a DN template, or a search account that finds the entry
 * first; the login escaped in the DN and in the filters; an empty password refused before any bind; attributes and groups read once
 * the person is bound; the password in no failure. The directory is FakeLdapConnection, which records every call.
 */
class LdapProviderTest extends TestCase
{
    private const URI = 'ldap://directory.acme.example:389';
    private const PASSWORD = 'Sésame-0uvre-toi';
    private const MARIE_DN = 'uid=marie,ou=people,dc=acme';
    private const MARIE = [
        'uid' => ['marie'],
        'mail' => ['marie.dupont@acme.example'],
        'givenName' => ['Marie'],
        'sn' => ['Dupont'],
        'memberOf' => ['cn=wiki-editors,ou=groups,dc=acme', 'cn=staff,ou=groups,dc=acme'],
        'userPassword' => [self::PASSWORD],
    ];

    private FakeLdapConnection $directory;

    protected function setUp(): void
    {
        $this->directory = new FakeLdapConnection();
        $this->directory->accounts[self::MARIE_DN] = self::PASSWORD;
        $this->directory->entries[self::MARIE_DN] = self::MARIE;
    }

    public function testDirectBindUsesTheTemplate(): void
    {
        $this->provider(['bind_dn_template' => 'uid={login},ou=people,dc=acme'])->authenticate(' marie ', self::PASSWORD);

        $this->assertSame([self::URI, false], $this->directory->connected);
        $this->assertSame(self::MARIE_DN, $this->directory->binds[0][0], 'the login is trimmed and put in the template');
        $this->assertCount(1, $this->directory->binds, 'no search account is involved');
    }

    public function testSearchThenBind(): void
    {
        $this->directory->accounts['cn=reader,dc=acme'] = 'reader-secret';
        $this->directory->entries['uid=marie,ou=staff,ou=people,dc=acme'] = self::MARIE;
        unset($this->directory->entries[self::MARIE_DN], $this->directory->accounts[self::MARIE_DN]);
        $this->directory->accounts['uid=marie,ou=staff,ou=people,dc=acme'] = self::PASSWORD;

        $identity = $this->provider([
            'search_dn' => 'cn=reader,dc=acme',
            'search_password' => 'reader-secret',
            'base_dn' => 'ou=people,dc=acme',
            'start_tls' => true,
        ])->authenticate('marie', self::PASSWORD);

        $this->assertSame([self::URI, true], $this->directory->connected);
        $this->assertSame([['cn=reader,dc=acme', 'reader-secret'], ['uid=marie,ou=staff,ou=people,dc=acme', self::PASSWORD]], $this->directory->binds);
        $this->assertSame(['ou=people,dc=acme', '(uid=marie)'], $this->directory->searches[0], 'the default user filter');
        $this->assertSame(['uid=marie,ou=staff,ou=people,dc=acme'], $identity->attributes['dn']);
    }

    public function testASearchWithoutAccountBindsAnonymously(): void
    {
        $this->provider(['base_dn' => 'ou=people,dc=acme', 'user_filter' => '(&(objectClass=inetOrgPerson)(uid={login}))'])->authenticate('marie', self::PASSWORD);

        $this->assertSame([null, null], $this->directory->binds[0]);
        $this->assertSame(['ou=people,dc=acme', '(&(objectClass=inetOrgPerson)(uid=marie))'], $this->directory->searches[0]);
        $this->assertSame([self::MARIE_DN, self::PASSWORD], $this->directory->binds[1]);
    }

    public function testAnEmptyPasswordNeverBinds(): void
    {
        foreach (['marie', ' marie '] as $login) {
            $failure = $this->failure(fn () => $this->provider()->authenticate($login, ''));

            $this->assertSame(FailureReason::WrongCredentials, $failure->reason);
        }
        $failure = $this->failure(fn () => $this->provider(['base_dn' => 'ou=people,dc=acme'])->authenticate('marie', ''));
        $this->assertSame(FailureReason::WrongCredentials, $failure->reason, 'nor does the search account');
        $failure = $this->failure(fn () => $this->provider()->authenticate('   ', self::PASSWORD));
        $this->assertSame(FailureReason::WrongCredentials, $failure->reason, 'an empty login neither');

        $this->assertSame([], $this->directory->binds, 'an anonymous bind would have passed for a success');
        $this->assertNull($this->directory->connected, 'the directory is not even contacted');
    }

    public function testTheLoginIsEscapedInDnAndFilter(): void
    {
        $login = 'a,b)(x*';
        $this->failure(fn () => $this->provider()->authenticate($login, self::PASSWORD));
        $this->assertSame('uid=a\2cb)(x*,ou=people,dc=acme', $this->directory->binds[0][0], 'escaped for a DN');

        $this->directory = new FakeLdapConnection();
        $this->failure(fn () => $this->provider(['base_dn' => 'ou=people,dc=acme'])->authenticate($login, self::PASSWORD));
        $this->assertSame('(uid=a,b\29\28x\2a)', $this->directory->searches[0][1], 'escaped for a filter');
    }

    public function testTheDnIsEscapedInTheGroupFilter(): void
    {
        $dn = 'uid=o(brien),ou=people,dc=acme';
        $this->directory->accounts[$dn] = self::PASSWORD;
        $this->directory->entries[$dn] = ['uid' => ['o(brien)']];

        $this->provider(['bind_dn_template' => 'uid={login},ou=people,dc=acme', 'group_base' => 'ou=groups,dc=acme', 'group_filter' => '(|(member={dn})(memberUid={login}))'])
            ->authenticate('o(brien)', self::PASSWORD);

        $this->assertSame(['ou=groups,dc=acme', '(|(member=uid=o\28brien\29,ou=people,dc=acme)(memberUid=o\28brien\29))'], end($this->directory->searches));
    }

    /**
     * A template converted from Doryphore may keep the spaces of its ldap_base. The directory binds a DN with spaces as the same
     * entry, and sends that entry back with the DN as it stores it: the entry must be found whatever the spaces.
     */
    public function testATemplateWithSpacesSignsIn(): void
    {
        $identity = $this->provider(['bind_dn_template' => 'uid={login}, ou=people , dc = acme'])->authenticate('marie', self::PASSWORD);

        $this->assertSame('uid=marie, ou=people , dc = acme', $this->directory->binds[0][0], 'the template is bound as written');
        $this->assertSame(['marie'], $identity->attributes['uid']);
        $this->assertSame(['marie.dupont@acme.example'], $identity->attributes['mail']);
    }

    public function testAttributesComeBackAfterBind(): void
    {
        $identity = $this->provider()->authenticate('marie', self::PASSWORD);

        $this->assertSame(['marie'], $identity->attributes['uid']);
        $this->assertSame(['marie.dupont@acme.example'], $identity->attributes['mail']);
        $this->assertSame(['Marie'], $identity->attributes['givenName']);
        $this->assertSame(['Dupont'], $identity->attributes['sn']);
        $this->assertSame(['cn=wiki-editors,ou=groups,dc=acme', 'cn=staff,ou=groups,dc=acme'], $identity->attributes['memberOf']);
        $this->assertSame([self::MARIE_DN], $identity->attributes['dn']);
        $this->assertSame([self::MARIE_DN], $this->directory->reads, 'the entry is read alone, as the person, once bound');
        $this->assertSame(self::MARIE_DN, $this->directory->boundAs[0], 'and only once bound');
    }

    public function testDefaultAttributesNameTheDirectorysAttributes(): void
    {
        $this->assertSame(
            ['id' => 'uid', 'email' => 'mail', 'first_name' => 'givenName', 'last_name' => 'sn', 'groups' => 'memberOf'],
            (new LdapType())->defaultAttributes()
        );
    }

    public function testGroupSearchFillsGroups(): void
    {
        $this->directory->groups = [
            'cn=wiki-editors,ou=groups,dc=acme' => ['cn' => ['wiki-editors']],
            'cn=staff,ou=groups,dc=acme' => ['cn' => ['staff']],
        ];

        $identity = $this->provider(['group_base' => 'ou=groups,dc=acme'], ['groups' => 'groups'])->authenticate('marie', self::PASSWORD);

        $this->assertSame(['ou=groups,dc=acme', '(member=uid=marie,ou=people,dc=acme)'], $this->directory->searches[0], 'the default group filter');
        $this->assertSame(['wiki-editors', 'staff'], $identity->attributes['groups'], 'each group by its cn');
        $this->assertSame(['marie'], $identity->attributes['uid'], 'beside the attributes');
    }

    public function testAGroupSearchReplacesMemberOf(): void
    {
        $this->directory->groups = ['cn=staff,ou=groups,dc=acme' => ['cn' => ['staff']]];

        $identity = $this->provider(['group_base' => 'ou=groups,dc=acme'])->authenticate('marie', self::PASSWORD);

        $this->assertSame(['staff'], $identity->attributes['memberOf'], 'the groups attribute is the search\'s, not the entry\'s');
    }

    public function testBadCredentialsAre49Or50(): void
    {
        foreach ([49 => 'Invalid credentials', 50 => 'Insufficient access'] as $code => $message) {
            $this->directory->refuseWith = [$code, $message];
            $failure = $this->failure(fn () => $this->provider()->authenticate('marie', self::PASSWORD));

            $this->assertSame(FailureReason::WrongCredentials, $failure->reason, "code $code");
            $this->assertNull($failure->employeeMessage, 'the person reads the usual "wrong login or password"');
            $this->assertStringContainsString($message, $failure->adminDetail);
        }
    }

    public function testAWrongPasswordIsWrongCredentials(): void
    {
        $failure = $this->failure(fn () => $this->provider()->authenticate('marie', 'not-the-password'));

        $this->assertSame(FailureReason::WrongCredentials, $failure->reason);
        $this->assertStringContainsString('Invalid credentials', $failure->adminDetail);
    }

    public function testAServerFailureIsUnreachableWithItsMessage(): void
    {
        $this->directory->refuseWith = [-1, "Can't contact LDAP server"];

        $failure = $this->failure(fn () => $this->provider()->authenticate('marie', self::PASSWORD));

        $this->assertSame(FailureReason::ProviderUnreachable, $failure->reason);
        $this->assertStringContainsString("Can't contact LDAP server", $failure->adminDetail);
        $this->assertStringContainsString(self::URI, $failure->adminDetail);
    }

    public function testAStartTlsFailureIsUnreachable(): void
    {
        $this->directory->startTlsFails = true;

        $failure = $this->failure(fn () => $this->provider(['start_tls' => true])->authenticate('marie', self::PASSWORD));

        $this->assertSame(FailureReason::ProviderUnreachable, $failure->reason);
        $this->assertStringContainsString('StartTLS', $failure->adminDetail);
        $this->assertSame([], $this->directory->binds, 'the password never travels in clear when StartTLS was asked for');
    }

    public function testARefusedSearchAccountIsConfigurationInvalid(): void
    {
        $this->directory->accounts['cn=reader,dc=acme'] = 'reader-secret';

        $failure = $this->failure(fn () => $this->provider(['search_dn' => 'cn=reader,dc=acme', 'search_password' => 'reader-typo', 'base_dn' => 'ou=people,dc=acme'])->authenticate('marie', self::PASSWORD));

        $this->assertSame(FailureReason::ConfigurationInvalid, $failure->reason, 'the person typed nothing wrong');
        $this->assertStringContainsString('search_dn', $failure->adminDetail);
        $this->assertStringContainsString('Invalid credentials', $failure->adminDetail);
        $this->assertStringNotContainsString('reader-typo', $failure->adminDetail, 'nor the search account\'s password');
        $this->assertCount(1, $this->directory->binds, 'the person\'s password is not tried');
    }

    public function testNoAccountInTheDirectoryIsWrongCredentials(): void
    {
        $failure = $this->failure(fn () => $this->provider(['base_dn' => 'ou=people,dc=acme'])->authenticate('nobody', self::PASSWORD));

        $this->assertSame(FailureReason::WrongCredentials, $failure->reason);
        $this->assertStringContainsString('(uid=nobody)', $failure->adminDetail);
        $this->assertCount(1, $this->directory->binds, 'nobody to bind as: only the anonymous bind of the search');
    }

    /**
     * An entry whose DN the client could not read comes back with an empty one. A bind with an empty DN and a password is an
     * unauthenticated bind, which many directories answer with success: the person would be in without their password being checked.
     */
    public function testAFoundEntryWithoutDnIsRefusedBeforeAnyBindWithThePassword(): void
    {
        foreach (['', '   '] as $dn) {
            $this->setUp();
            $this->directory->personSearchAnswers = [['dn' => $dn, 'attributes' => self::MARIE]];

            $failure = $this->failure(fn () => $this->provider(['base_dn' => 'ou=people,dc=acme'])->authenticate('marie', self::PASSWORD));

            $this->assertSame(FailureReason::InvalidResponse, $failure->reason, "DN \"$dn\"");
            $this->assertStringContainsString('no DN', $failure->adminDetail);
            $this->assertSame([[null, null]], $this->directory->binds, 'only the anonymous bind of the search: the password is never sent');
        }
    }

    public function testSeveralEntriesForOneLoginIsConfigurationInvalid(): void
    {
        $this->directory->entries['uid=marie,ou=former,dc=acme'] = self::MARIE;

        $failure = $this->failure(fn () => $this->provider(['base_dn' => 'dc=acme'])->authenticate('marie', self::PASSWORD));

        $this->assertSame(FailureReason::ConfigurationInvalid, $failure->reason);
        $this->assertStringContainsString('user_filter', $failure->adminDetail);
        $this->assertCount(1, $this->directory->binds, 'neither entry is tried');
    }

    public function testAMissingModuleIsConfigurationInvalid(): void
    {
        $provider = new LdapProvider($this->config(), $this->directory, fn (): bool => false);

        $failure = $this->failure(fn () => $provider->authenticate('marie', self::PASSWORD));

        $this->assertSame(FailureReason::ConfigurationInvalid, $failure->reason);
        $this->assertStringContainsString('ldap', $failure->adminDetail);
        $this->assertNull($this->directory->connected);
    }

    public function testAnIncompleteConnectionIsConfigurationInvalid(): void
    {
        foreach ([
            'uri' => ['uri' => ''],
            'ldap://' => ['uri' => 'https://directory.acme.example'],
            'bind_dn_template' => ['bind_dn_template' => ''],
            '{login}' => ['bind_dn_template' => 'cn=everyone,dc=acme'],
        ] as $named => $connection) {
            $failure = $this->failure(fn () => $this->provider($connection)->authenticate('marie', self::PASSWORD));

            $this->assertSame(FailureReason::ConfigurationInvalid, $failure->reason, $named);
            $this->assertStringContainsString($named, $failure->adminDetail);
        }
        $this->assertNull($this->directory->connected);
    }

    public function testThePasswordIsNotInAnyFailure(): void
    {
        $scenarios = [
            'wrong password' => fn () => $this->directory->refuseWith = [49, 'Invalid credentials'],
            'server down' => fn () => $this->directory->refuseWith = [-1, "Can't contact LDAP server"],
            'a server quoting it' => fn () => $this->directory->refuseWith = [53, 'Unwilling to perform: password ' . self::PASSWORD . ' is too weak'],
            'unreadable entry' => fn () => $this->directory->entries = [],
            'module missing' => fn () => null,
        ];
        foreach ($scenarios as $name => $arrange) {
            $this->setUp();
            $arrange();
            $provider = $name === 'module missing'
                ? new LdapProvider($this->config(), $this->directory, fn (): bool => false)
                : $this->provider();

            try {
                $provider->authenticate('marie', self::PASSWORD);
                $this->fail("$name: no failure");
            } catch (ProviderFailure $failure) {
                // the arguments the provider's own calls had, as an error log would print them: the fixtures further up are the test's
                $providerFrames = array_filter($failure->getTrace(), fn (array $frame): bool => ($frame['class'] ?? '') === LdapProvider::class);
                $said = $failure->adminDetail . "\n" . $failure->getMessage() . "\n" . $failure->employeeMessage . "\n" . json_encode($failure->attributes)
                    . "\n" . $failure->getTraceAsString() . "\n" . print_r($providerFrames, true);
                $this->assertStringNotContainsString(self::PASSWORD, $said, $name);
                $this->assertStringNotContainsString(rawurlencode(self::PASSWORD), $said, $name);
            }
        }
    }

    public function testThePasswordIsNotInTheIdentity(): void
    {
        $identity = $this->provider()->authenticate('marie', self::PASSWORD);

        $this->assertArrayNotHasKey('userPassword', $identity->attributes, 'the directory may let a person read their own password');
        $this->assertStringNotContainsString(self::PASSWORD, (string)json_encode($identity->attributes));
        $this->assertStringNotContainsString(self::PASSWORD, print_r($identity, true));
    }

    public function testTheProviderSaysWhoItIs(): void
    {
        $provider = $this->provider();

        $this->assertSame('ldap', $provider->id());
        $this->assertSame('Acme directory', $provider->label());
        $this->assertSame('ldap', $provider->type());
    }

    /**
     * @param array<string, mixed>  $connection merged over a direct bind to the fake directory
     * @param array<string, string> $attributes
     */
    private function provider(array $connection = [], array $attributes = []): LdapProvider
    {
        return new LdapProvider($this->config($connection, $attributes), $this->directory, fn (): bool => true);
    }

    /**
     * @param array<string, mixed>  $connection
     * @param array<string, string> $attributes
     */
    private function config(array $connection = [], array $attributes = []): ProviderConfig
    {
        $base = isset($connection['base_dn']) ? [] : ['bind_dn_template' => 'uid={login},ou=people,dc=acme'];

        return ProviderConfig::fromArray('ldap', [
            'type' => 'ldap',
            'label' => 'Acme directory',
            'connection' => array_merge(['uri' => self::URI], $base, $connection),
            'attributes' => $attributes,
        ], (new LdapType())->defaultAttributes());
    }

    private function failure(callable $sign): ProviderFailure
    {
        try {
            $identity = $sign();
        } catch (ProviderFailure $failure) {
            return $failure;
        }
        $this->fail('no failure, but ' . ($identity instanceof ProviderIdentity ? json_encode($identity->attributes) : get_debug_type($identity)));
    }
}

/**
 * A directory in memory: entries by DN, the passwords that bind them, groups with a member list.
 * Searches understand only what the provider sends: `(uid=...)` and `(member=...)` under a base. A DN is compared as a directory
 * does: whatever its case and the spaces around `,` and `=`.
 */
final class FakeLdapConnection implements LdapConnection
{
    /** @var array<string, string> DN => password */
    public array $accounts = [];
    /** @var array<string, array<string, list<string>>> */
    public array $entries = [];
    /** @var array<string, array<string, list<string>>> group DN => attributes; every group has every person as member */
    public array $groups = [];
    /** @var ?array{0: int, 1: string} what every bind answers, whatever the password */
    public ?array $refuseWith = null;
    public bool $startTlsFails = false;
    /** @var ?list<array{dn: string, attributes: array<string, list<string>>}> what the search for a person answers, whatever it asks */
    public ?array $personSearchAnswers = null;

    /** @var ?array{0: string, 1: bool} */
    public ?array $connected = null;
    /** @var list<array{0: ?string, 1: ?string}> */
    public array $binds = [];
    /** @var list<array{0: string, 1: string}> */
    public array $searches = [];
    /** @var list<string> */
    public array $reads = [];
    /** @var list<?string> who the connection was bound as at each search or read */
    public array $boundAs = [];

    private ?string $bound = null;
    private int $code = 0;
    private string $message = 'Success';

    public function connect(string $uri, bool $startTls): void
    {
        $this->connected = [$uri, $startTls];
        if ($startTls && $this->startTlsFails) {
            $this->code = -11;
            $this->message = 'Connect error';
            throw new \RuntimeException('StartTLS failed: Connect error');
        }
    }

    public function bind(?string $dn, #[\SensitiveParameter] ?string $password): bool
    {
        $this->binds[] = [$dn, $password];
        if ($this->refuseWith !== null) {
            [$this->code, $this->message] = $this->refuseWith;

            return false;
        }
        // as many directories do: no DN, or an empty one, is an anonymous or unauthenticated bind, and succeeds whatever the password
        // a DN names the same entry whatever its case and the spaces around its separators, as a directory compares them
        $account = $dn === null ? null : $this->stored($dn, array_keys($this->accounts));
        if ($dn === null || trim($dn) === '' || ($account !== null && $this->accounts[$account] === $password)) {
            $this->bound = $dn;
            [$this->code, $this->message] = [0, 'Success'];

            return true;
        }
        [$this->code, $this->message] = [49, 'Invalid credentials'];

        return false;
    }

    public function search(string $base, string $filter): array
    {
        $this->searches[] = [$base, $filter];
        $this->boundAs[] = $this->bound;
        [$this->code, $this->message] = [0, 'Success'];
        $under = fn (string $dn): bool => str_ends_with(strtolower($dn), strtolower($base));
        $found = [];

        if (str_contains($filter, 'member=')) {
            foreach ($this->groups as $dn => $attributes) {
                if ($under($dn)) {
                    $found[] = ['dn' => $dn, 'attributes' => $attributes];
                }
            }

            return $found;
        }
        if ($this->personSearchAnswers !== null && str_contains($filter, '(uid=')) {
            return $this->personSearchAnswers;
        }
        if (preg_match('/\(uid=([^)]*)\)/', $filter, $uid)) {
            foreach ($this->entries as $dn => $attributes) {
                if ($under($dn) && in_array($uid[1], $attributes['uid'] ?? [], true)) {
                    $found[] = ['dn' => $dn, 'attributes' => $attributes];
                }
            }
        }

        return $found;
    }

    public function read(string $dn): ?array
    {
        $this->reads[] = $dn;
        $this->boundAs[] = $this->bound;
        $stored = $this->stored($dn, array_keys($this->entries));
        if ($stored === null) {
            [$this->code, $this->message] = [32, 'No such object'];

            return null;
        }
        [$this->code, $this->message] = [0, 'Success'];

        return ['dn' => $stored, 'attributes' => $this->entries[$stored]];
    }

    public function lastErrorCode(): int
    {
        return $this->code;
    }

    public function lastError(): string
    {
        return $this->message;
    }

    /**
     * The DN among $stored that $dn names, as the directory stores it.
     *
     * @param list<string> $stored
     */
    private function stored(string $dn, array $stored): ?string
    {
        $normal = static fn (string $dn): string => strtolower((string)preg_replace('/\s*([,=])\s*/', '$1', trim($dn)));
        foreach ($stored as $candidate) {
            if ($normal($candidate) === $normal($dn)) {
                return $candidate;
            }
        }

        return null;
    }
}
