<?php

namespace YesWiki\Identity\SignIn\Provider;

/**
 * LdapConnection through ext-ldap: LDAP v3, no referral followed (it would bind again, elsewhere), and 5 seconds to reach the server
 * or answer a search. The module's warnings are kept quiet: what went wrong is read from lastErrorCode() and lastError().
 */
final class NativeLdapConnection implements LdapConnection
{
    private const TIMEOUT = 5;

    /** Asked by name: a memberOf maintained by the server is an operational attribute, which `*` leaves out. */
    private const ATTRIBUTES = ['*', 'memberOf'];

    private ?\LDAP\Connection $link = null;
    private int $code = 0;
    private string $message = '';

    public function connect(string $uri, bool $startTls): void
    {
        $this->link = null;
        $this->code = 0;
        $this->message = '';
        $link = @ldap_connect($uri);
        if ($link === false) {
            $this->code = -1;
            $this->message = 'the address is not one the LDAP client takes';

            throw new \RuntimeException($this->message);
        }
        ldap_set_option($link, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($link, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($link, LDAP_OPT_NETWORK_TIMEOUT, self::TIMEOUT);
        ldap_set_option($link, LDAP_OPT_TIMELIMIT, self::TIMEOUT);
        $this->link = $link;
        if ($startTls && !@ldap_start_tls($link)) {
            $this->remember();
            $this->link = null;

            throw new \RuntimeException('StartTLS failed: ' . $this->message);
        }
    }

    public function bind(?string $dn, #[\SensitiveParameter] ?string $password): bool
    {
        if ($this->link === null) {
            return $this->notConnected();
        }
        $bound = @ldap_bind($this->link, $dn, $password);
        $this->remember();

        return $bound;
    }

    public function search(string $base, string $filter): array
    {
        $link = $this->link;
        if ($link === null) {
            $this->notConnected();

            return [];
        }
        $result = @ldap_search($link, $base, $filter, self::ATTRIBUTES, 0, 0, self::TIMEOUT);
        $this->remember();

        return $result instanceof \LDAP\Result ? $this->entries($link, $result) : [];
    }

    public function read(string $dn): ?array
    {
        $link = $this->link;
        if ($link === null) {
            $this->notConnected();

            return null;
        }
        $result = @ldap_read($link, $dn, '(objectClass=*)', self::ATTRIBUTES, 0, 0, self::TIMEOUT);
        $this->remember();

        return $result instanceof \LDAP\Result ? ($this->entries($link, $result)[0] ?? null) : null;
    }

    /** @return list<array{dn: string, attributes: array<string, list<string>>}> */
    private function entries(\LDAP\Connection $link, \LDAP\Result $result): array
    {
        $entries = [];
        for ($entry = @ldap_first_entry($link, $result); $entry instanceof \LDAP\ResultEntry; $entry = @ldap_next_entry($link, $entry)) {
            $dn = ldap_get_dn($link, $entry);
            $entries[] = ['dn' => is_string($dn) ? $dn : '', 'attributes' => self::values(ldap_get_attributes($link, $entry))];
        }

        return $entries;
    }

    public function lastErrorCode(): int
    {
        return $this->code;
    }

    public function lastError(): string
    {
        return $this->message;
    }

    /** The result of the operation just made, and the server's own words about it when it sent some. */
    private function remember(): void
    {
        if ($this->link === null) {
            return;
        }
        $this->code = ldap_errno($this->link);
        $this->message = ldap_err2str($this->code);
        $diagnostic = null;
        if ($this->code !== 0 && @ldap_get_option($this->link, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnostic) && is_string($diagnostic) && trim($diagnostic) !== '') {
            $this->message .= ' (' . trim($diagnostic) . ')';
        }
    }

    private function notConnected(): bool
    {
        $this->code = -1;
        $this->message = 'not connected';

        return false;
    }

    /**
     * ext-ldap's entry, counts and positions included, as attribute name => values.
     *
     * @param array<int|string, mixed> $raw
     *
     * @return array<string, list<string>>
     */
    private static function values(array $raw): array
    {
        $attributes = [];
        foreach ($raw as $name => $values) {
            if (!is_string($name) || !is_array($values)) {
                continue;
            }
            unset($values['count']);
            $attributes[$name] = array_values(array_filter($values, 'is_string'));
        }

        return $attributes;
    }
}
