<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use YesWiki\Identity\SignIn\Provider\LdapConnection\NativeLdapConnection;

require_once __DIR__ . '/LdapStubServer.php';

/**
 * NativeLdapConnection through ext-ldap and libldap, against a directory that answers what each test tells it to (LdapStubServer):
 * what goes on the wire, and how the directory's answers come back. What the provider makes of them is LdapProviderTest's.
 * The certificate of an ldaps:// directory is checked against a TLS server with a self-signed certificate, run by a child PHP
 * process: the handshake is all that test needs.
 */
#[RequiresPhpExtension('ldap')]
class NativeLdapConnectionTest extends TestCase
{
    private const MARIE_DN = 'uid=marie,ou=people,dc=acme,dc=example';

    /** @var resource|null */
    private $server;
    private string $pem = '';
    private ?LdapStubServer $directory = null;

    protected function tearDown(): void
    {
        $this->directory?->stop();
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        if ($this->pem !== '' && is_file($this->pem)) {
            unlink($this->pem);
        }
        // libldap's own default, which ext-ldap can set but not read
        ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND);
    }

    public function testABindSendsTheDnAndThePasswordInLdapV3(): void
    {
        $connection = $this->connectedTo([['op' => 'bind', 'code' => 0]]);

        $this->assertTrue($connection->bind(self::MARIE_DN, 'TestPassword1'));

        $this->assertSame(0, $connection->lastErrorCode());
        $this->assertSame([['op' => 'bind', 'version' => 3, 'dn' => self::MARIE_DN, 'auth' => 'simple', 'password' => true]], $this->requests());
    }

    public function testANullDnAndPasswordIsAnAnonymousBind(): void
    {
        $connection = $this->connectedTo([['op' => 'bind', 'code' => 0]]);

        $this->assertTrue($connection->bind(null, null));

        $this->assertSame([['op' => 'bind', 'version' => 3, 'dn' => '', 'auth' => 'simple', 'password' => false]], $this->requests());
    }

    public function testARefusedBindTellsTheCodeAndWhatTheServerAdded(): void
    {
        $connection = $this->connectedTo([['op' => 'bind', 'code' => 49, 'diagnostic' => 'AcceptSecurityContext error, data 52e']]);

        $this->assertFalse($connection->bind(self::MARIE_DN, 'wrong'));

        $this->assertSame(49, $connection->lastErrorCode());
        $this->assertSame(ldap_err2str(49) . ' (AcceptSecurityContext error, data 52e)', $connection->lastError());
    }

    public function testASearchAsksForEveryAttributeAndMemberOfUnderTheBase(): void
    {
        $connection = $this->connectedTo([
            ['op' => 'bind', 'code' => 0],
            ['op' => 'search', 'code' => 0, 'entries' => [
                ['dn' => 'cn=wiki-editors,ou=groups,dc=acme,dc=example', 'attributes' => ['cn' => ['wiki-editors'], 'objectClass' => ['top', 'groupOfNames']]],
                ['dn' => 'cn=staff,ou=groups,dc=acme,dc=example', 'attributes' => ['cn' => ['staff']]],
            ]],
        ]);
        $connection->bind(null, null);

        $found = $connection->search('ou=groups,dc=acme,dc=example', '(member=uid\\3dmarie\\2cou\\3dpeople)');

        $this->assertSame([
            ['dn' => 'cn=wiki-editors,ou=groups,dc=acme,dc=example', 'attributes' => ['cn' => ['wiki-editors'], 'objectClass' => ['top', 'groupOfNames']]],
            ['dn' => 'cn=staff,ou=groups,dc=acme,dc=example', 'attributes' => ['cn' => ['staff']]],
        ], $found, 'every value of every attribute, without the counts and positions of ext-ldap');
        $this->assertSame(0, $connection->lastErrorCode());
        $this->assertSame([
            'op' => 'search',
            'base' => 'ou=groups,dc=acme,dc=example',
            'scope' => 2,
            'sizeLimit' => 0,
            'timeLimit' => 5,
            'typesOnly' => false,
            'filter' => '(member=uid=marie,ou=people)',
            'attributes' => ['*', 'memberOf'],
        ], $this->requests()[1], 'subtree, 5 seconds, memberOf asked by name since `*` leaves it out');
    }

    public function testAFilterReachesTheServerAsWritten(): void
    {
        $connection = $this->connectedTo([['op' => 'bind', 'code' => 0], ['op' => 'search', 'code' => 0]]);
        $connection->bind(null, null);

        $filter = '(&(objectClass=inetOrgPerson)(|(uid=ma\\2arie)(mail=marie@*))(!(cn=x)))';
        $this->assertSame([], $connection->search('ou=people,dc=acme,dc=example', $filter));

        $this->assertSame($filter, $this->requests()[1]['filter']);
    }

    public function testAttributeNamesKeepTheCaseTheServerGives(): void
    {
        $connection = $this->connectedTo([
            ['op' => 'bind', 'code' => 0],
            ['op' => 'search', 'code' => 0, 'entries' => [['dn' => self::MARIE_DN, 'attributes' => [
                'uid' => ['marie'],
                'givenName' => ['Marie'],
                'MAIL' => ['marie.dupont@acme.example', 'm.dupont@acme.example'],
                'memberOf' => ['cn=staff,ou=groups,dc=acme,dc=example'],
            ]]]],
        ]);
        $connection->bind(null, null);

        $found = $connection->search('ou=people,dc=acme,dc=example', '(uid=marie)');

        $this->assertSame([
            'uid' => ['marie'],
            'givenName' => ['Marie'],
            'MAIL' => ['marie.dupont@acme.example', 'm.dupont@acme.example'],
            'memberOf' => ['cn=staff,ou=groups,dc=acme,dc=example'],
        ], $found[0]['attributes']);
    }

    public function testAFailedSearchIsAnEmptyListWithItsCode(): void
    {
        $connection = $this->connectedTo([['op' => 'bind', 'code' => 0], ['op' => 'search', 'code' => 32, 'matchedDn' => 'dc=acme,dc=example']]);
        $connection->bind(null, null);

        $this->assertSame([], $connection->search('ou=nobody,dc=acme,dc=example', '(uid=marie)'));

        $this->assertSame(32, $connection->lastErrorCode());
        $this->assertSame(ldap_err2str(32), $connection->lastError());
    }

    public function testAReadAsksForTheEntryAloneByItsDn(): void
    {
        $connection = $this->connectedTo([
            ['op' => 'bind', 'code' => 0],
            ['op' => 'search', 'code' => 0, 'entries' => [['dn' => self::MARIE_DN, 'attributes' => ['uid' => ['marie'], 'sn' => ['Dupont']]]]],
        ]);
        $connection->bind(self::MARIE_DN, 'TestPassword1');

        // the directory compares DNs itself: the entry comes back under the DN it stores
        $entry = $connection->read('uid=marie, ou=people, dc=acme, dc=example');

        $this->assertSame(['dn' => self::MARIE_DN, 'attributes' => ['uid' => ['marie'], 'sn' => ['Dupont']]], $entry);
        $this->assertSame([
            'op' => 'search',
            'base' => 'uid=marie, ou=people, dc=acme, dc=example',
            'scope' => 0,
            'sizeLimit' => 0,
            'timeLimit' => 5,
            'typesOnly' => false,
            'filter' => '(objectClass=*)',
            'attributes' => ['*', 'memberOf'],
        ], $this->requests()[1]);
    }

    public function testAReadTheDirectoryAnswersWithNoEntryIsNullWithoutError(): void
    {
        $connection = $this->connectedTo([['op' => 'bind', 'code' => 0], ['op' => 'search', 'code' => 0]]);
        $connection->bind(self::MARIE_DN, 'TestPassword1');

        $this->assertNull($connection->read(self::MARIE_DN));
        $this->assertSame(0, $connection->lastErrorCode());
    }

    public function testAFailedReadIsNullWithItsCode(): void
    {
        $connection = $this->connectedTo([['op' => 'bind', 'code' => 0], ['op' => 'search', 'code' => 50, 'diagnostic' => 'no read access to the entry']]);
        $connection->bind(self::MARIE_DN, 'TestPassword1');

        $this->assertNull($connection->read(self::MARIE_DN));
        $this->assertSame(50, $connection->lastErrorCode());
        $this->assertSame(ldap_err2str(50) . ' (no read access to the entry)', $connection->lastError());
    }

    public function testADirectoryThatDropsTheConnectionIsNotReached(): void
    {
        $connection = $this->connectedTo([['op' => 'bind', 'close' => true]]);

        $this->assertFalse($connection->bind(self::MARIE_DN, 'TestPassword1'));

        $this->assertLessThan(0, $connection->lastErrorCode(), 'a negative code: the client, not the server, says so');
    }

    public function testADirectoryNobodyListensAtIsNotReached(): void
    {
        $connection = new NativeLdapConnection();
        $connection->connect('ldap://127.0.0.1:' . LdapStubServer::closedPort(), false);

        $this->assertFalse($connection->bind(self::MARIE_DN, 'TestPassword1'));

        $this->assertSame(-1, $connection->lastErrorCode());
        $this->assertSame(ldap_err2str(-1), $connection->lastError());
    }

    public function testAStartTlsTheDirectoryRefusesStopsTheConnection(): void
    {
        $this->directory = new LdapStubServer([['op' => 'starttls', 'code' => 52, 'diagnostic' => 'TLS is not configured']]);
        $connection = new NativeLdapConnection();

        try {
            $connection->connect($this->directory->uri(), true);
            $this->fail('StartTLS was refused, the connection is not made');
        } catch (\RuntimeException $failed) {
            $this->assertSame('StartTLS failed: ' . ldap_err2str(52) . ' (TLS is not configured)', $failed->getMessage());
        }

        $this->assertSame(52, $connection->lastErrorCode());
        $this->assertSame([['op' => 'starttls', 'name' => '1.3.6.1.4.1.1466.20037']], $this->requests());
        $this->assertFalse($connection->bind(self::MARIE_DN, 'TestPassword1'), 'nothing is sent unencrypted after it');
        $this->assertSame('not connected', $connection->lastError());
    }

    public function testNothingIsAskedBeforeConnecting(): void
    {
        $connection = new NativeLdapConnection();

        $this->assertFalse($connection->bind(self::MARIE_DN, 'TestPassword1'));
        $this->assertSame([], $connection->search('dc=acme,dc=example', '(uid=marie)'));
        $this->assertNull($connection->read(self::MARIE_DN));
        $this->assertSame(-1, $connection->lastErrorCode());
        $this->assertSame('not connected', $connection->lastError());
    }

    public function testAnAddressTheClientDoesNotTakeIsRefusedAtConnect(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the address is not one the LDAP client takes');

        (new NativeLdapConnection())->connect('http://directory.acme.example', false);
    }

    /**
     * TLS_REQCERT never in ldap.conf, or LDAPTLS_REQCERT=never in the server's environment, would let any certificate through, and
     * the docs promise the directory's certificate is checked.
     */
    #[RequiresPhpExtension('openssl')]
    public function testACertificateNobodyVouchesForIsRefused(): void
    {
        ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        $connection = new NativeLdapConnection();

        $connection->connect('ldaps://127.0.0.1:' . $this->selfSignedServer(), false);
        $bound = $connection->bind('uid=marie,ou=people,dc=acme', 'any');

        $this->assertFalse($bound);
        $this->assertSame(-1, $connection->lastErrorCode(), 'the directory is not reached');
        $this->assertStringContainsString('certificate verify failed', $connection->lastError());
    }

    /** @param list<array<string, mixed>> $replies */
    private function connectedTo(array $replies): NativeLdapConnection
    {
        $this->directory = new LdapStubServer($replies);
        $connection = new NativeLdapConnection();
        $connection->connect($this->directory->uri(), false);

        return $connection;
    }

    /** @return list<array<string, mixed>> */
    private function requests(): array
    {
        $this->assertNotNull($this->directory);

        return $this->directory->requests();
    }

    /** A TLS server on a free port, which accepts one connection and makes the handshake: the port. */
    private function selfSignedServer(): int
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $csr = openssl_csr_new(['commonName' => '127.0.0.1'], $key);
        $this->assertInstanceOf(\OpenSSLCertificateSigningRequest::class, $csr);
        $certificate = openssl_csr_sign($csr, null, $key, 1);
        $this->assertNotFalse($certificate);
        openssl_x509_export($certificate, $pem);
        openssl_pkey_export($key, $privateKey);
        $this->pem = (string)tempnam(sys_get_temp_dir(), 'yw-ldap-tls');
        file_put_contents($this->pem, $pem . $privateKey);

        $script = <<<'PHP'
            $context = stream_context_create(['ssl' => ['local_cert' => $argv[1]]]);
            $server = stream_socket_server('tcp://127.0.0.1:0', $no, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
            echo substr(strrchr(stream_socket_get_name($server, false), ':'), 1), "\n";
            $client = stream_socket_accept($server, 10);
            if ($client) {
                @stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
                fclose($client);
            }
            PHP;
        $server = proc_open([PHP_BINARY, '-r', $script, $this->pem], [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        $this->assertIsResource($server);
        $this->server = $server;
        $port = (int)trim((string)fgets($pipes[1]));
        $this->assertGreaterThan(0, $port, 'the TLS server started');

        return $port;
    }
}
