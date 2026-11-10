<?php

namespace YesWiki\Identity\SignIn\Provider;

use YesWiki\Identity\SignIn\Contract\FailureReason;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderFailure;
use YesWiki\Identity\SignIn\Contract\ProviderIdentity;

/**
 * A company directory checking the login and password typed into the wiki's form (spec sections 6 and 12).
 *
 * - The person is found by `bind_dn_template`, or by a search under `base_dn` with `user_filter`, bound as `search_dn` or anonymously.
 * - The login is escaped where it goes: LDAP_ESCAPE_DN in a DN, LDAP_ESCAPE_FILTER in a filter; so is the DN in `group_filter`.
 * - An empty password is refused before any bind: a directory takes a DN with no password as an anonymous bind, and says yes.
 * - The entry is read once the person is bound, as them. Groups are its `memberOf`, or with `group_base` the cn of each group a search
 *   with `group_filter` finds, put under the attribute the provider's `groups` names.
 * - The password is never kept: no failure, attribute or message holds it, and password attributes of the entry are left out.
 */
final class LdapProvider implements PasswordProvider
{
    public const DEFAULT_USER_FILTER = '(uid={login})';
    public const DEFAULT_GROUP_FILTER = '(member={dn})';

    /** A wrong password, or an account the directory will not let in. */
    private const REFUSED_CODES = [49, 50];

    /** Busy, unavailable, server down, timeout, connect error: the directory was not there to answer. Negative codes are the client's. */
    private const UNREACHABLE_CODES = [51, 52, 81, 85, 91];

    /** Attributes an entry may hold a password or a key in, which a person can sometimes read of their own entry. Compared lowercased. */
    private const SECRET_ATTRIBUTES = ['userpassword', 'authpassword', 'unicodepwd', 'sambantpassword', 'sambalmpassword', 'userpkcs12', 'krbprincipalkey'];

    /** @var \Closure(): bool */
    private readonly \Closure $moduleLoaded;

    /** @param ?\Closure(): bool $moduleLoaded whether ext-ldap is there; a seam, since a test cannot unload it */
    public function __construct(
        private readonly ProviderConfig $config,
        private readonly LdapConnection $connection,
        ?\Closure $moduleLoaded = null,
    ) {
        $this->moduleLoaded = $moduleLoaded ?? static fn (): bool => extension_loaded('ldap');
    }

    public function id(): string
    {
        return $this->config->id;
    }

    public function label(): string
    {
        return $this->config->label;
    }

    public function type(): string
    {
        return $this->config->type;
    }

    public function authenticate(string $login, #[\SensitiveParameter] string $password): ProviderIdentity
    {
        if (!($this->moduleLoaded)()) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'the PHP ldap extension is not loaded: install and enable it to use an LDAP directory');
        }
        $login = trim($login);
        if ($login === '' || $password === '') {
            throw new ProviderFailure(FailureReason::WrongCredentials, 'an empty login or password is refused before the directory is asked');
        }
        $settings = $this->settings();

        try {
            $this->connection->connect($settings['uri'], $settings['start_tls']);
        } catch (\RuntimeException $failed) {
            throw new ProviderFailure(FailureReason::ProviderUnreachable, "the directory at {$settings['uri']} could not be reached: " . $this->scrub($failed->getMessage(), $password));
        }

        $dn = $settings['template'] !== ''
            ? str_replace('{login}', ldap_escape($login, '', LDAP_ESCAPE_DN), $settings['template'])
            : $this->find($settings, $login, $password);

        // a bind with an empty DN and a password is unauthenticated, and many directories answer it with success
        if (trim($dn) === '') {
            throw new ProviderFailure(FailureReason::InvalidResponse, 'no DN to bind the person as: the password is not sent');
        }
        if (!$this->connection->bind($dn, $password)) {
            throw $this->bindFailure($settings, "the directory refused \"$dn\"", $password, FailureReason::WrongCredentials);
        }

        $attributes = $this->entry($settings, $dn, $password);
        if ($settings['group_base'] !== '') {
            $groupsAttribute = $this->config->attributes['groups'] ?? 'memberOf';
            $attributes[$groupsAttribute] = $this->groups($settings, $login, $dn, $password);
        }

        return new ProviderIdentity(['dn' => [$dn]] + $attributes);
    }

    /**
     * The connection settings, checked before the directory is contacted.
     *
     * @return array{uri: string, start_tls: bool, template: string, search_dn: string, search_password: string, base_dn: string, user_filter: string, group_base: string, group_filter: string}
     */
    private function settings(): array
    {
        $text = fn (string $key, string $default = ''): string => is_scalar($value = $this->config->connection($key)) && trim((string)$value) !== '' ? trim((string)$value) : $default;
        $settings = [
            'uri' => $text('uri'),
            'start_tls' => filter_var($this->config->connection('start_tls', false), FILTER_VALIDATE_BOOLEAN),
            'template' => $text('bind_dn_template'),
            'search_dn' => $text('search_dn'),
            'search_password' => is_string($secret = $this->config->connection('search_password')) ? $secret : '',
            'base_dn' => $text('base_dn'),
            'user_filter' => $text('user_filter', self::DEFAULT_USER_FILTER),
            'group_base' => $text('group_base'),
            'group_filter' => $text('group_filter', self::DEFAULT_GROUP_FILTER),
        ];

        if ($settings['uri'] === '') {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'no uri: set the directory\'s address, like ldaps://ldap.example.org');
        }
        if (!preg_match('#^ldaps?://[^\s]+$#i', $settings['uri'])) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "uri \"{$settings['uri']}\" is not a directory's address: write it ldap://host:port or ldaps://host:port");
        }
        if ($settings['template'] === '' && $settings['base_dn'] === '') {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'neither bind_dn_template nor base_dn: set a bind_dn_template like uid={login},ou=people,dc=example,dc=org, or a base_dn to search the person under');
        }
        if ($settings['template'] !== '' && !str_contains($settings['template'], '{login}')) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "bind_dn_template \"{$settings['template']}\" has no {login}: everyone would bind as the same DN");
        }
        if ($settings['template'] === '' && !str_contains($settings['user_filter'], '{login}')) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, "user_filter \"{$settings['user_filter']}\" has no {login}: it would find the same entry for everyone");
        }
        if ($settings['search_dn'] !== '' && $settings['search_password'] === '') {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, 'search_dn has no search_password: a DN without a password is an anonymous bind');
        }

        return $settings;
    }

    /**
     * The DN of the one entry user_filter finds under base_dn, bound as the search account or anonymously.
     *
     * @param array{uri: string, start_tls: bool, template: string, search_dn: string, search_password: string, base_dn: string, user_filter: string, group_base: string, group_filter: string} $settings
     */
    private function find(array $settings, string $login, #[\SensitiveParameter] string $password): string
    {
        $account = $settings['search_dn'] !== '' ? $settings['search_dn'] : null;
        if (!$this->connection->bind($account, $account === null ? null : $settings['search_password'])) {
            throw $this->bindFailure($settings, $account === null ? 'the directory refused the anonymous bind of the search: set search_dn and search_password' : "the directory refused the search account \"$account\": check search_dn and search_password", $password, FailureReason::ConfigurationInvalid);
        }
        $filter = str_replace('{login}', ldap_escape($login, '', LDAP_ESCAPE_FILTER), $settings['user_filter']);
        $found = $this->search($settings, $settings['base_dn'], $filter, $password, 'search the person');
        if ($found === []) {
            throw new ProviderFailure(FailureReason::WrongCredentials, "no entry under \"{$settings['base_dn']}\" matches $filter");
        }
        if (count($found) > 1) {
            throw new ProviderFailure(FailureReason::ConfigurationInvalid, sprintf('%d entries under "%s" match %s: user_filter must find one person only', count($found), $settings['base_dn'], $filter));
        }

        if (trim($found[0]['dn']) === '') {
            throw new ProviderFailure(FailureReason::InvalidResponse, "the entry under \"{$settings['base_dn']}\" matching $filter came back with no DN: there is nobody to bind as");
        }

        return $found[0]['dn'];
    }

    /**
     * The person's entry, read as them, without its password attributes.
     *
     * @param array{uri: string, start_tls: bool, template: string, search_dn: string, search_password: string, base_dn: string, user_filter: string, group_base: string, group_filter: string} $settings
     *
     * @return array<string, list<string>>
     */
    private function entry(array $settings, string $dn, #[\SensitiveParameter] string $password): array
    {
        foreach ($this->search($settings, $dn, '(objectClass=*)', $password, 'read the person\'s entry') as $entry) {
            if (strcasecmp($entry['dn'], $dn) === 0) {
                return array_filter(
                    $entry['attributes'],
                    static fn (string $name): bool => !in_array(strtolower($name), self::SECRET_ATTRIBUTES, true),
                    ARRAY_FILTER_USE_KEY
                );
            }
        }

        throw new ProviderFailure(FailureReason::InvalidResponse, "the directory let \"$dn\" in, but sent no entry for it: its access rules may not let a person read their own entry");
    }

    /**
     * The name of each group group_filter finds under group_base: its cn, or its DN when it has none.
     *
     * @param array{uri: string, start_tls: bool, template: string, search_dn: string, search_password: string, base_dn: string, user_filter: string, group_base: string, group_filter: string} $settings
     *
     * @return list<string>
     */
    private function groups(array $settings, string $login, string $dn, #[\SensitiveParameter] string $password): array
    {
        $filter = strtr($settings['group_filter'], [
            '{dn}' => ldap_escape($dn, '', LDAP_ESCAPE_FILTER),
            '{login}' => ldap_escape($login, '', LDAP_ESCAPE_FILTER),
        ]);
        $names = [];
        foreach ($this->search($settings, $settings['group_base'], $filter, $password, 'search the person\'s groups') as $group) {
            $names[] = $group['attributes']['cn'][0] ?? $group['dn'];
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array{uri: string, start_tls: bool, template: string, search_dn: string, search_password: string, base_dn: string, user_filter: string, group_base: string, group_filter: string} $settings
     *
     * @return list<array{dn: string, attributes: array<string, list<string>>}>
     */
    private function search(array $settings, string $base, string $filter, #[\SensitiveParameter] string $password, string $doing): array
    {
        $found = $this->connection->search($base, $filter);
        $code = $this->connection->lastErrorCode();
        // 4: the size limit was reached, and what came back is still the server's answer
        if ($found === [] && $code !== 0 && $code !== 4) {
            throw $this->failure($settings, $code, "the directory could not $doing under \"$base\" with $filter", $password, FailureReason::InvalidResponse);
        }

        return $found;
    }

    /** @param array{uri: string, start_tls: bool, template: string, search_dn: string, search_password: string, base_dn: string, user_filter: string, group_base: string, group_filter: string} $settings */
    private function bindFailure(array $settings, string $what, #[\SensitiveParameter] string $password, FailureReason $refused): ProviderFailure
    {
        $code = $this->connection->lastErrorCode();

        return $this->failure($settings, $code, $what, $password, in_array($code, self::REFUSED_CODES, true) ? $refused : FailureReason::ProviderRejected);
    }

    /**
     * A failure named by the LDAP result code: unreachable when the directory was not there to answer, $otherwise when it was.
     *
     * @param array{uri: string, start_tls: bool, template: string, search_dn: string, search_password: string, base_dn: string, user_filter: string, group_base: string, group_filter: string} $settings
     */
    private function failure(array $settings, int $code, string $what, #[\SensitiveParameter] string $password, FailureReason $otherwise): ProviderFailure
    {
        $said = $this->scrub($this->connection->lastError(), $password);
        if ($code < 0 || in_array($code, self::UNREACHABLE_CODES, true)) {
            return new ProviderFailure(FailureReason::ProviderUnreachable, "the directory at {$settings['uri']} could not be reached: $said ($code)");
        }

        return new ProviderFailure($otherwise, "$what: $said ($code)");
    }

    /**
     * What the server or the client said, unless it quotes the password: then none of it, since taking the password out of it would
     * still tell where it was.
     */
    private function scrub(string $said, #[\SensitiveParameter] string $password): string
    {
        $said = trim(preg_replace('/\s+/', ' ', $said) ?? '');
        if (str_contains($said, $password) || str_contains($said, rawurlencode($password))) {
            return '(the message is left out: it quoted the password)';
        }

        return $said !== '' ? mb_substr($said, 0, 300) : 'no message';
    }
}
