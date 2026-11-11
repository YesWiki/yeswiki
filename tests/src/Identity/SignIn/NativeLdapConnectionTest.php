<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use YesWiki\Identity\SignIn\Provider\NativeLdapConnection;

/**
 * ext-ldap's connection checks the certificate of an ldaps:// directory whatever libldap was told elsewhere. The directory is a TLS
 * server with a self-signed certificate, run by a child PHP process: the handshake is all the test needs. The E2E signs in against
 * the development directory, which is plain ldap://.
 */
#[RequiresPhpExtension('ldap')]
#[RequiresPhpExtension('openssl')]
class NativeLdapConnectionTest extends TestCase
{
    /** @var resource|null */
    private $server;
    private string $pem = '';

    protected function tearDown(): void
    {
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

    /**
     * TLS_REQCERT never in ldap.conf, or LDAPTLS_REQCERT=never in the server's environment, would let any certificate through, and
     * the docs promise the directory's certificate is checked.
     */
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
