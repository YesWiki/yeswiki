<?php

namespace YesWiki\Identity\SignIn\Provider\LdapConnection;

/** What LdapProvider asks of a directory, one connection per sign-in. */
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
     *
     * @return list<array{dn: string, attributes: array<string, list<string>>}>
     */
    public function search(string $base, string $filter): array;

    /**
     * The entry $dn names, read alone (base scope), as the connection is bound now.
     *
     * @return ?array{dn: string, attributes: array<string, list<string>>}
     */
    public function read(string $dn): ?array;

    /** The LDAP result code of the last operation: 0 for success, 49 for invalid credentials, negative when the server was not reached. */
    public function lastErrorCode(): int;

    /** The text of that result, and what the server added to it. */
    public function lastError(): string;
}
