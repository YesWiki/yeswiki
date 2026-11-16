<?php

namespace YesWiki\Identity\SignIn\Provider\LdapConnection;

/**
 * What LdapProvider asks of a directory, one connection per sign-in. Nothing here escapes a value: the provider hands DNs and
 * filters already built. A failed bind or search is told by lastErrorCode() and lastError(), never by an exception.
 *
 * Why an interface here when the OIDC, SAML and CAS providers have none:
 * - Those providers hand their protocol to a library (league/oauth2-client, onelogin/php-saml, phpCAS) and reach the outside only
 *   over HTTP, through a client the library already lets them inject: their tests swap that client, with no interface of ours.
 * - LdapProvider does the protocol itself: it builds the DN or searches for it, escapes the login, refuses an empty password, reads
 *   the entry, finds the groups, drops the password attributes and sorts the result codes. Those rules want a test each.
 * - ext-ldap is global functions over final objects (\LDAP\Connection, \LDAP\Result), which no test can stand in for. This interface
 *   is the seam: NativeLdapConnection is the only real one, a thin wrapper tested against a stub server, and LdapProviderTest drives
 *   the provider through a fake that answers any code (49 against 50, a search failing after a bind) a real server rarely gives.
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

    /**
     * The entry $dn names, read alone (base scope), as the connection is bound now. The directory compares DNs itself, so spaces
     * around `,` and `=` or a different case still name the entry; its DN comes back as the directory stores it.
     * Null with lastErrorCode() not 0 is a failed read; null with 0 is an entry the directory did not send.
     *
     * @return ?array{dn: string, attributes: array<string, list<string>>}
     */
    public function read(string $dn): ?array;

    /** The LDAP result code of the last operation: 0 for success, 49 for invalid credentials, negative when the server was not reached. */
    public function lastErrorCode(): int;

    /** The text of that result, and what the server added to it. */
    public function lastError(): string;
}
