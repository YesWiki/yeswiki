<?php

namespace YesWiki\Identity\SignIn\Provider;

/**
 * What LdapProvider asks of a directory, one connection per sign-in. Nothing here escapes a value: the provider hands DNs and
 * filters already built. A failed bind or search is told by lastErrorCode() and lastError(), never by an exception.
 */
interface LdapConnection
{
    /**
     * Prepares the connection, and with $startTls makes it encrypted before anything is sent.
     *
     * @throws \RuntimeException when the address is not one the client takes, or StartTLS fails; its message says why
     */
    public function connect(string $uri, bool $startTls): void;

    /** A null DN and password is an anonymous bind. */
    public function bind(?string $dn, #[\SensitiveParameter] ?string $password): bool;

    /**
     * The entries under $base (included) matching $filter, as the connection is bound now.
     * Attribute names keep the case the server gives them; an empty list with lastErrorCode() not 0 is a failed search.
     *
     * @return list<array{dn: string, attributes: array<string, list<string>>}>
     */
    public function search(string $base, string $filter): array;

    /** The LDAP result code of the last operation: 0 for success, 49 for invalid credentials, negative when the server was not reached. */
    public function lastErrorCode(): int;

    /** The text of that result, and what the server added to it. */
    public function lastError(): string;
}
