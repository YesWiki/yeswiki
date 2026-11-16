<?php

/*
 * A directory that says what it is told to: an LDAPv3 server over plain TCP that answers each request with the next reply of a
 * script, so that NativeLdapConnection is tested through ext-ldap and libldap without a real directory. Run by LdapStubServer:
 *
 *     php stub-server.php <replies.json> <requests.log>
 *
 * It listens on a free port of 127.0.0.1, prints it on a line, then serves one connection after the other until every reply is
 * used, or 10 seconds pass without a connection. A reply is {"op": "bind"|"search"|"starttls", "code": int, "diagnostic"?: string,
 * "matchedDn"?: string, "entries"?: [{"dn": string, "attributes": {name: [values]}}]}, or {"op": ..., "close": true} to drop the
 * connection instead of answering. Each request is appended to the log as a line of JSON, before the reply is sent; a request the
 * script did not expect is logged as {"error": ...} and ends the connection. A bind's password is never logged, only whether
 * there was one.
 */

const TIMEOUT = 10;
const START_TLS = '1.3.6.1.4.1.1466.20037';

[, $repliesFile, $logFile] = $argv + [null, '', ''];
$script = json_decode((string)file_get_contents((string)$repliesFile), true);
if (!is_array($script)) {
    fwrite(STDERR, "usage: php stub-server.php <replies.json> <requests.log>\n");
    exit(2);
}
/** @var list<array<string, mixed>> $replies */
$replies = [];
foreach ($script as $reply) {
    if (is_array($reply)) {
        $replies[] = $reply;
    }
}

$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($server === false) {
    fwrite(STDERR, "cannot listen: $errorMessage\n");
    exit(1);
}
echo substr((string)strrchr((string)stream_socket_get_name($server, false), ':'), 1), "\n";
flush();

$log = static function (array $line) use ($logFile): void {
    file_put_contents((string)$logFile, json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
};

while (($client = @stream_socket_accept($server, TIMEOUT)) !== false) {
    stream_set_timeout($client, TIMEOUT);
    serve($client, $replies, $log);
    @fclose($client);
    if ($replies === []) {
        break;
    }
}

/**
 * Answers the requests of one connection, until it is closed, unbound, or a reply says to drop it.
 *
 * @param resource                    $client
 * @param list<array<string, mixed>>  $replies the replies not used yet
 * @param Closure(array<mixed>): void $log
 */
function serve($client, array &$replies, Closure $log): void
{
    while (($message = readElement($client)) !== null) {
        [$tag, $content] = $message;
        if ($tag !== 0x30) {
            $log(['error' => sprintf('an LDAPMessage is a SEQUENCE, not tag 0x%02x', $tag)]);

            return;
        }
        $parts = elements($content);
        $id = decodeInteger($parts[0][1] ?? '');
        [$opTag, $op] = $parts[1] ?? [0, ''];

        if ($opTag === 0x42) {
            $log(['op' => 'unbind']);

            return;
        }
        $request = match ($opTag) {
            0x60 => bindRequest($op),
            0x63 => searchRequest($op),
            0x77 => extendedRequest($op),
            default => ['op' => sprintf('application %d', $opTag & 0x1F)],
        };
        $reply = array_shift($replies);
        if ($reply === null || ($reply['op'] ?? null) !== $request['op']) {
            $log(['error' => sprintf('a %s request came, the script expected %s', $request['op'], $reply === null ? 'nothing more' : 'a ' . json_encode($reply['op'] ?? null))] + $request);

            return;
        }
        $log($request);
        if (($reply['close'] ?? false) === true) {
            return;
        }

        $result = ldapResult($reply);
        $answer = match ($request['op']) {
            'bind' => [element(0x61, $result)],
            'search' => [...array_map(entry(...), is_array($reply['entries'] ?? null) ? $reply['entries'] : []), element(0x65, $result)],
            default => [element(0x78, $result . element(0x8A, START_TLS))],
        };
        foreach ($answer as $op) {
            fwrite($client, element(0x30, element(0x02, encodeInteger($id)) . $op));
        }
    }
}

/** @return array{op: string, version: int, dn: string, auth: string, password: bool} */
function bindRequest(string $content): array
{
    $parts = elements($content);
    [$authTag, $credentials] = $parts[2] ?? [0, ''];

    return [
        'op' => 'bind',
        'version' => decodeInteger($parts[0][1] ?? ''),
        'dn' => $parts[1][1] ?? '',
        'auth' => $authTag === 0x80 ? 'simple' : 'sasl',
        'password' => $credentials !== '',
    ];
}

/** @return array{op: string, base: string, scope: int, sizeLimit: int, timeLimit: int, typesOnly: bool, filter: string, attributes: list<string>} */
function searchRequest(string $content): array
{
    $parts = elements($content);

    return [
        'op' => 'search',
        'base' => $parts[0][1] ?? '',
        'scope' => decodeInteger($parts[1][1] ?? ''),
        'sizeLimit' => decodeInteger($parts[3][1] ?? ''),
        'timeLimit' => decodeInteger($parts[4][1] ?? ''),
        'typesOnly' => ($parts[5][1] ?? "\0") !== "\0",
        'filter' => filter($parts[6] ?? [0, '']),
        'attributes' => array_map(static fn (array $attribute): string => $attribute[1], elements($parts[7][1] ?? '')),
    ];
}

/** @return array{op: string, name: string} */
function extendedRequest(string $content): array
{
    $name = elements($content)[0][1] ?? '';

    return ['op' => $name === START_TLS ? 'starttls' : "extended $name", 'name' => $name];
}

/**
 * A filter as RFC 4515 writes it, values escaped as ldap_escape() does; a kind of filter the provider never sends, as its hex.
 *
 * @param array{0: int, 1: string} $filter
 */
function filter(array $filter): string
{
    [$tag, $content] = $filter;
    $escape = static fn (string $value): string => (string)preg_replace_callback('/[\\\\*()\0]/', static fn (array $c): string => sprintf('\\%02x', ord($c[0])), $value);

    return match ($tag) {
        0xA0, 0xA1 => '(' . ($tag === 0xA0 ? '&' : '|') . implode('', array_map(filter(...), elements($content))) . ')',
        0xA2 => '(!' . filter(elements($content)[0] ?? [0, '']) . ')',
        0xA3 => (static function () use ($content, $escape): string {
            $assertion = elements($content);

            return '(' . ($assertion[0][1] ?? '') . '=' . $escape($assertion[1][1] ?? '') . ')';
        })(),
        0xA4 => (static function () use ($content, $escape): string {
            $parts = elements($content);
            $initial = $final = '';
            $any = [];
            foreach (elements($parts[1][1] ?? '') as [$tag, $value]) {
                match ($tag) {
                    0x80 => $initial = $escape($value),
                    0x82 => $final = $escape($value),
                    default => $any[] = $escape($value),
                };
            }

            return '(' . ($parts[0][1] ?? '') . '=' . implode('*', [$initial, ...$any, $final]) . ')';
        })(),
        0x87 => "($content=*)",
        default => sprintf('0x%02x:%s', $tag, bin2hex($content)),
    };
}

/** @param array<string, mixed> $reply */
function ldapResult(array $reply): string
{
    $code = is_int($reply['code'] ?? null) ? $reply['code'] : 0;

    return element(0x0A, encodeInteger($code))
        . element(0x04, is_string($reply['matchedDn'] ?? null) ? $reply['matchedDn'] : '')
        . element(0x04, is_string($reply['diagnostic'] ?? null) ? $reply['diagnostic'] : '');
}

/** @param array{dn?: string, attributes?: array<string, list<string>>} $entry a SearchResultEntry */
function entry(array $entry): string
{
    $attributes = '';
    foreach ($entry['attributes'] ?? [] as $name => $values) {
        $set = implode('', array_map(static fn (string $value): string => element(0x04, $value), $values));
        $attributes .= element(0x30, element(0x04, (string)$name) . element(0x31, $set));
    }

    return element(0x64, element(0x04, $entry['dn'] ?? '') . element(0x30, $attributes));
}

/**
 * The next BER element of the connection: its tag and its content. Null at the end of the connection.
 *
 * @param resource $client
 *
 * @return ?array{0: int, 1: string}
 */
function readElement($client): ?array
{
    $head = readBytes($client, 2);
    if ($head === null) {
        return null;
    }
    $length = ord($head[1]);
    if ($length & 0x80) {
        $bytes = readBytes($client, $length & 0x7F);
        if ($bytes === null) {
            return null;
        }
        $length = 0;
        foreach (str_split($bytes) as $byte) {
            $length = ($length << 8) | ord($byte);
        }
    }
    $content = $length === 0 ? '' : readBytes($client, $length);

    return $content === null ? null : [ord($head[0]), $content];
}

/** @param resource $client */
function readBytes($client, int $count): ?string
{
    $bytes = '';
    while (strlen($bytes) < $count) {
        $chunk = fread($client, max(1, $count - strlen($bytes)));
        if ($chunk === false || $chunk === '') {
            return null;
        }
        $bytes .= $chunk;
    }

    return $bytes;
}

/**
 * The elements one after the other in $content: the members of a SEQUENCE or a SET.
 *
 * @return list<array{0: int, 1: string}>
 */
function elements(string $content): array
{
    $elements = [];
    $at = 0;
    while ($at + 2 <= strlen($content)) {
        $tag = ord($content[$at]);
        $length = ord($content[$at + 1]);
        $at += 2;
        if ($length & 0x80) {
            $size = $length & 0x7F;
            $length = 0;
            for ($i = 0; $i < $size; $i++) {
                $length = ($length << 8) | ord($content[$at + $i] ?? "\0");
            }
            $at += $size;
        }
        $elements[] = [$tag, substr($content, $at, $length)];
        $at += $length;
    }

    return $elements;
}

function element(int $tag, string $content): string
{
    $length = strlen($content);
    if ($length < 0x80) {
        return chr($tag) . chr($length) . $content;
    }
    $bytes = ltrim(pack('N', $length), "\0");

    return chr($tag) . chr(0x80 | strlen($bytes)) . $bytes . $content;
}

function encodeInteger(int $value): string
{
    $bytes = ltrim(pack('N', $value), "\0");
    if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
        $bytes = "\0" . $bytes;
    }

    return $bytes;
}

function decodeInteger(string $bytes): int
{
    $value = 0;
    foreach ($bytes === '' ? [] : str_split($bytes) as $byte) {
        $value = ($value << 8) | ord($byte);
    }

    return $value;
}
