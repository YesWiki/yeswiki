<?php

namespace YesWiki\Identity\Service\SignIn;

use Symfony\Component\String\Slugger\AsciiSlugger;
use YesWiki\Identity\Service\SignIn\Type\CasType;
use YesWiki\Identity\Service\SignIn\Type\LdapType;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\Service\SignIn\Type\OidcType;
use YesWiki\Identity\SignIn\SsoConversionResult;

/**
 * Doryphore's loginsso, loginldap and logincas settings, as `sso_providers` entries (spec section 11).
 * Pure: it reads the configuration array it is given and returns what to write, the database and the files are the migration's.
 */
final class DoryphoreSsoConversion
{
    public const LOGINSSO = 'loginsso';
    public const LOGINLDAP = 'loginldap';
    public const LOGINCAS = 'logincas';

    /** Doryphore loaded its tools in alphabetical order and the last one's login action won: loginsso, then loginldap, then logincas. */
    public const PRECEDENCE = [self::LOGINSSO, self::LOGINLDAP, self::LOGINCAS];

    public const SSO_KEY = 'sso_config';
    public const LDAP_KEYS = ['ldap_host', 'ldap_port', 'ldap_base', 'ldap_group', 'ldap_organisation'];
    public const CAS_KEYS = ['cas_host', 'cas_port', 'cas_context', 'cas_server_ca_cert_path', 'cas_bazar_mapping'];

    /** How long LDAP and CAS may still find an unlinked account by its name, as they did. */
    public const MATCH_BY_NAME_FOR = '+12 months';

    /** What logincas and the CAS protocol 2.0 client of Doryphore used. */
    private const CAS_VERSION = '2.0';

    /**
     * @param array<string, mixed> $config         the whole configuration
     * @param list<string>         $installedTools the Doryphore tool folders this instance had
     */
    public function convert(array $config, array $installedTools, \DateTimeImmutable $now): SsoConversionResult
    {
        $ssoEntries = $this->ssoEntries($config);
        $configured = [];
        if ($ssoEntries !== []) {
            $configured[] = self::LOGINSSO;
        }
        if (self::text($config['ldap_host'] ?? null) !== '') {
            $configured[] = self::LOGINLDAP;
        }
        if (self::text($config['cas_host'] ?? null) !== '') {
            $configured[] = self::LOGINCAS;
        }
        if ($configured === []) {
            return new SsoConversionResult([], [], [], null, [], null);
        }

        $existing = is_array($config['sso_providers'] ?? null) && $config['sso_providers'] !== [] ? $config['sso_providers'] : null;
        $doryphoreInCharge = null;
        foreach ($configured as $tool) {
            if (in_array($tool, $installedTools, true)) {
                $doryphoreInCharge = $tool;
                break;
            }
        }
        // A section the administrator already wrote is what signs people in now: the converted providers join it switched off.
        $inCharge = $existing === null ? $doryphoreInCharge : null;

        $taken = array_fill_keys(array_map('strval', array_keys($existing ?? [])), true) + [LocalType::NAME => true];
        $ldapId = in_array(self::LOGINLDAP, $configured, true) ? self::free(LdapType::NAME, $taken) : null;
        $casId = in_array(self::LOGINCAS, $configured, true) ? self::free(CasType::NAME, $taken) : null;
        $until = $now->modify(self::MATCH_BY_NAME_FOR)->format('Y-m-d');

        $converted = [];
        $origins = [];
        $loginssoIds = [];
        foreach ($ssoEntries as $entry) {
            $label = self::text($entry['button_style']['button_label'] ?? null);
            $id = self::free(self::slug($label) ?: OidcType::NAME, $taken);
            $converted[$id] = $this->oidc($entry, $label, $inCharge === self::LOGINSSO);
            $origins[$id] = self::LOGINSSO;
            $loginssoIds[] = $id;
        }
        if ($ldapId !== null) {
            $converted[$ldapId] = $this->ldap($config, $until, $inCharge === self::LOGINLDAP);
            $origins[$ldapId] = self::LOGINLDAP;
        }
        if ($casId !== null) {
            $converted[$casId] = $this->cas($config, $until, $inCharge === self::LOGINCAS);
            $origins[$casId] = self::LOGINCAS;
        }

        $providers = $existing === null
            ? [LocalType::NAME => ['type' => LocalType::NAME, 'enabled' => $inCharge === null]] + $converted
            : $existing + $converted;

        return new SsoConversionResult(
            $providers,
            $this->removedKeys($config, $origins),
            $this->messages($config, $converted, $origins, $inCharge, $existing !== null, $until),
            $inCharge,
            $loginssoIds,
            $doryphoreInCharge
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array<string, mixed>>
     */
    private function ssoEntries(array $config): array
    {
        $providers = $config[self::SSO_KEY]['providers'] ?? null;
        if (!is_array($providers)) {
            return [];
        }

        return array_values(array_filter($providers, 'is_array'));
    }

    /**
     * @param array<string, mixed> $entry one of `sso_config.providers`
     *
     * @return array<string, mixed>
     */
    private function oidc(array $entry, string $label, bool $enabled): array
    {
        $options = is_array($entry['auth_options'] ?? null) ? $entry['auth_options'] : [];
        $connection = [
            'authorize_url' => self::text($options['urlAuthorize'] ?? null),
            'token_url' => self::text($options['urlAccessToken'] ?? null),
            'userinfo_url' => self::text($options['urlResourceOwnerDetails'] ?? null),
            'client_id' => self::text($options['clientId'] ?? null),
            'client_secret' => self::text($options['clientSecret'] ?? null),
            // loginsso asked for `openid` alone when nothing was set: asking for more could change what the provider consents to.
            'scopes' => isset($options['scopes']) && (is_array($options['scopes']) || is_string($options['scopes'])) ? $options['scopes'] : ['openid'],
        ];
        $separator = $options['scopeSeparator'] ?? null;
        if (is_string($separator) && $separator !== '') {
            $connection['scope_separator'] = $separator;
        }

        $fields = is_array($entry['bazar_mapping']['fields'] ?? null) ? $entry['bazar_mapping']['fields'] : [];
        $attributes = [
            'id' => self::text($entry['id_sso_field'] ?? null) ?: 'id',
            'email' => self::text($entry['email_sso_field'] ?? null) ?: 'email',
            'first_name' => self::attributeOf($fields['bf_prenom'] ?? null) ?: 'given_name',
            'last_name' => self::attributeOf($fields['bf_nom'] ?? null) ?: 'family_name',
        ];
        $groups = self::text($entry['groups_sso_field'] ?? null);
        if ($groups !== '') {
            $attributes['groups'] = $groups;
        }

        $provider = ['type' => OidcType::NAME];
        if ($label !== '') {
            $provider['label'] = $label;
        }
        $provider += [
            'enabled' => $enabled,
            'connection' => $connection,
            'attributes' => $attributes,
            'create_accounts' => false,
            // loginsso attached an account by its email whenever it had no link yet.
            'match_by_email' => true,
            'group_mapping' => self::groupMapping($entry['groups_sso_mapping'] ?? null),
        ];
        $template = self::text($entry['create_user_from'] ?? null);
        if ($template !== '') {
            $provider['create_accounts'] = true;
            $provider['account_name_template'] = $template;
        }

        return $provider;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function ldap(array $config, string $until, bool $enabled): array
    {
        $base = self::text($config['ldap_base'] ?? null);
        if ($base !== '') {
            $template = 'uid={login},' . $base;
        } else {
            $parts = ['uid={login}'];
            $group = self::text($config['ldap_group'] ?? null);
            $organisation = self::text($config['ldap_organisation'] ?? null);
            if ($group !== '') {
                $parts[] = 'ou=' . $group;
            }
            if ($organisation !== '') {
                $parts[] = 'o=' . $organisation;
            }
            $template = implode(',', $parts);
        }
        $template = self::dn($template);

        return [
            'type' => LdapType::NAME,
            'label' => 'LDAP',
            'enabled' => $enabled,
            'connection' => [
                'uri' => self::ldapUri(self::text($config['ldap_host'] ?? null), self::text($config['ldap_port'] ?? null)),
                'bind_dn_template' => $template,
            ],
            'attributes' => ['id' => 'uid', 'email' => 'mail', 'first_name' => 'givenName', 'last_name' => 'sn'],
            'create_accounts' => true,
            'match_by_email' => true,
            'match_by_name_until' => $until,
            'group_mapping' => [],
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function cas(array $config, string $until, bool $enabled): array
    {
        $host = (string)preg_replace('#^https?://#i', '', rtrim(self::text($config['cas_host'] ?? null), '/'));
        $port = self::text($config['cas_port'] ?? null) ?: '443';
        $context = trim(self::text($config['cas_context'] ?? null) ?: '/cas', '/');
        $connection = [
            'server_url' => 'https://' . $host . ':' . $port . ($context === '' ? '' : '/' . $context),
            'version' => self::CAS_VERSION,
        ];
        $ca = self::text($config['cas_server_ca_cert_path'] ?? null);
        if ($ca !== '') {
            $connection['ca_cert'] = $ca;
        }

        // logincas named the account after the `name` attribute and read the email from `mail`.
        $attributes = ['id' => 'name', 'email' => 'mail'];
        $fields = self::casBazarFields($config['cas_bazar_mapping'] ?? null);
        if (($first = self::attributeOf($fields['bf_prenom'] ?? null)) !== '') {
            $attributes['first_name'] = $first;
        }
        if (($last = self::attributeOf($fields['bf_nom'] ?? null)) !== '') {
            $attributes['last_name'] = $last;
        }

        return [
            'type' => CasType::NAME,
            'label' => 'CAS',
            'enabled' => $enabled,
            'connection' => $connection,
            'attributes' => $attributes,
            'create_accounts' => true,
            'match_by_email' => true,
            'match_by_name_until' => $until,
            'group_mapping' => [],
        ];
    }

    /**
     * @param array<string, mixed>  $config
     * @param array<string, string> $origins the extension each converted provider came from
     *
     * @return list<string>
     */
    private function removedKeys(array $config, array $origins): array
    {
        $keys = [];
        if (in_array(self::LOGINSSO, $origins, true)) {
            $keys[] = self::SSO_KEY;
        }
        if (in_array(self::LOGINLDAP, $origins, true)) {
            $keys = array_merge($keys, self::LDAP_KEYS);
        }
        if (in_array(self::LOGINCAS, $origins, true)) {
            $keys = array_merge($keys, self::CAS_KEYS);
        }

        return array_values(array_filter($keys, fn (string $key): bool => array_key_exists($key, $config)));
    }

    /**
     * Only identifiers, switches, dates and key names: never a client ID, secret or password.
     *
     * @param array<string, mixed>                $config
     * @param array<string, array<string, mixed>> $converted
     * @param array<string, string>               $origins
     *
     * @return list<string>
     */
    private function messages(array $config, array $converted, array $origins, ?string $inCharge, bool $hadSection, string $until): array
    {
        $listed = [];
        foreach ($converted as $id => $provider) {
            $listed[] = sprintf('%s (%s, from %s, %s)', $id, $provider['type'], $origins[$id], $provider['enabled'] ? 'on' : 'off');
        }
        $messages = ['the login extensions became sign-in providers in sso_providers: ' . implode(', ', $listed) . '.'];

        if ($hadSection) {
            $messages[] = 'sso_providers already existed: its entries are unchanged, and the converted providers were added switched off.';
        } elseif ($inCharge !== null) {
            $messages[] = "{$inCharge} signed people in, so its provider is on and local accounts are off: if its server is down, nobody can sign in until `local` is switched on in sso_providers.";
        } else {
            $messages[] = 'none of these extensions was still installed, so their providers are off and local accounts stay on.';
        }

        $byName = array_keys(array_filter($converted, fn (array $provider): bool => isset($provider['match_by_name_until'])));
        if ($byName !== []) {
            $messages[] = implode(' and ', $byName) . " find an unlinked account named after the person's user ID until {$until}, as Doryphore did, and by email after that.";
        }

        $dropped = [];
        $sso = is_array($config[self::SSO_KEY] ?? null) ? $config[self::SSO_KEY] : [];
        foreach (['login_username_initials', 'bazar_user_entry_id'] as $key) {
            if (array_key_exists($key, $sso)) {
                $dropped[] = $key;
            }
        }
        $bazarMapping = false;
        $transformed = false;
        foreach ($this->ssoEntries($config) as $entry) {
            if (isset($entry['bazar_mapping'])) {
                $bazarMapping = true;
                $transformed = $transformed || isset($entry['bazar_mapping']['fields_transformed']);
            }
        }
        if ($transformed) {
            $dropped[] = 'fields_transformed';
        }
        if ($dropped !== []) {
            $messages[] = 'dropped, with nothing to replace them: ' . implode(', ', $dropped) . '.';
        }
        if ($bazarMapping) {
            $messages[] = "loginsso's bazar_mapping is not carried over: the entries it created stay as ordinary content, and sign-ins no longer create any.";
        }

        if (in_array(self::LOGINCAS, $origins, true)) {
            if (isset($config['cas_bazar_mapping']) && $config['cas_bazar_mapping'] !== []) {
                $messages[] = "logincas's cas_bazar_mapping is not carried over: the entries it created stay as ordinary content, and sign-ins no longer create any.";
            }
            $messages[] = "logincas's /api/participant/* is gone: it listed the accounts' emails to anonymous visitors.";
            if (self::text($config['cas_server_ca_cert_path'] ?? null) === '') {
                $messages[] = "cas_server_ca_cert_path was empty, which turned certificate checks off on Doryphore: the CAS server is now checked against the system's authorities, so a server on a private authority refuses sign-ins until connection.ca_cert names its certificate.";
            }
            $messages[] = "a CAS server that restricts its services must now allow this wiki's sign-in address for the CAS provider.";
        }

        return $messages;
    }

    private static function ldapUri(string $host, string $port): string
    {
        if (preg_match('#^ldaps?://#i', $host) === 1) {
            $host = rtrim($host, '/');

            return $port === '' || preg_match('#:\d+$#', $host) === 1 ? $host : $host . ':' . $port;
        }

        return 'ldap://' . $host . ':' . ($port === '' ? '389' : $port);
    }

    /**
     * loginsso's `groups_sso_mapping`: provider group => wiki group.
     *
     * @return list<array{provider: string, wiki: string}>
     */
    private static function groupMapping(mixed $mapping): array
    {
        if (!is_array($mapping)) {
            return [];
        }
        $rows = [];
        foreach ($mapping as $providerGroup => $wikiGroups) {
            foreach (is_array($wikiGroups) ? $wikiGroups : [$wikiGroups] as $wikiGroup) {
                $wiki = self::text($wikiGroup);
                if ($wiki !== '' && (string)$providerGroup !== '') {
                    $rows[] = ['provider' => (string)$providerGroup, 'wiki' => $wiki];
                }
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> the fields of logincas's mapping, a list of one form's mapping */
    private static function casBazarFields(mixed $mapping): array
    {
        if (!is_array($mapping)) {
            return [];
        }
        $first = isset($mapping['fields']) ? $mapping : ($mapping[0] ?? null);

        return is_array($first) && is_array($first['fields'] ?? null) ? $first['fields'] : [];
    }

    /** A mapped attribute, written `given_name` or `#[given_name]`. */
    private static function attributeOf(mixed $value): string
    {
        $text = self::text($value);

        return preg_match('/^#\[([^\]]+)\]$/', $text, $match) === 1 ? $match[1] : $text;
    }

    /** @param array<string, bool> $taken */
    private static function free(string $base, array &$taken): string
    {
        $id = $base;
        for ($n = 2; isset($taken[$id]); $n++) {
            $id = $base . '-' . $n;
        }
        $taken[$id] = true;

        return $id;
    }

    private static function slug(string $label): string
    {
        return mb_strtolower((new AsciiSlugger())->slug($label)->toString());
    }

    /**
     * A DN without the spaces around its `,` and `=`, which loginldap's settings were often written with. A directory reads both as
     * the same DN, but sends the entry back as it stores it, without them. An escaped `\,` or `\=`, and the spaces inside a value,
     * are kept.
     */
    private static function dn(string $dn): string
    {
        $dn = (string)preg_replace('/(?<!\\\\)\s+(?=[,=])/', '', $dn);

        return (string)preg_replace('/(?<!\\\\)([,=])\s+/', '$1', $dn);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? trim((string)$value) : '';
    }
}
