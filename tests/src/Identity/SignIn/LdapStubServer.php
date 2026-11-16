<?php

namespace YesWiki\Test\Identity\SignIn;

/**
 * A directory for one test: tests/fixtures/ldap/stub-server.php run by a child PHP process, which answers ext-ldap's requests
 * with the replies it is given, in order, and logs what it was asked. See that script for the shape of a reply.
 */
final class LdapStubServer
{
    private const SCRIPT = __DIR__ . '/../../../fixtures/ldap/stub-server.php';

    /** @var resource|null */
    private $process;
    private string $replies;
    private string $log;
    private int $port;

    /** @param list<array<string, mixed>> $replies */
    public function __construct(array $replies)
    {
        $this->replies = (string)tempnam(sys_get_temp_dir(), 'yw-ldap-replies');
        $this->log = (string)tempnam(sys_get_temp_dir(), 'yw-ldap-requests');
        file_put_contents($this->replies, json_encode($replies, JSON_THROW_ON_ERROR));

        $process = proc_open(
            [PHP_BINARY, self::SCRIPT, $this->replies, $this->log],
            [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('the LDAP stub server did not start');
        }
        $this->process = $process;
        $this->port = (int)trim((string)fgets($pipes[1]));
        if ($this->port <= 0) {
            $this->stop();
            throw new \RuntimeException('the LDAP stub server gave no port');
        }
    }

    /** A port of 127.0.0.1 nobody listens on: it was free a moment ago, and is closed again. */
    public static function closedPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('no free port');
        }
        $port = (int)substr((string)strrchr((string)stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    public function uri(): string
    {
        return 'ldap://127.0.0.1:' . $this->port;
    }

    /**
     * What the server was asked, in order, unbinds left out (the client sends them when it is done, maybe after this is read).
     *
     * @return list<array<string, mixed>>
     */
    public function requests(): array
    {
        $requests = [];
        foreach (file($this->log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($request)) {
                continue;
            }
            if (isset($request['error'])) {
                throw new \LogicException('the LDAP stub server was asked what its script did not expect: ' . $line);
            }
            if (($request['op'] ?? null) !== 'unbind') {
                $requests[] = $request;
            }
        }

        return $requests;
    }

    /** The whole log, as written: to check something is not in it. */
    public function log(): string
    {
        return (string)file_get_contents($this->log);
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        $this->process = null;
        foreach ([$this->replies, $this->log] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
