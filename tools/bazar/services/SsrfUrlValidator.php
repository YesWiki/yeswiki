<?php

namespace YesWiki\Bazar\Service;

/**
 * Validates that a URL is safe to fetch server-side: it must use a scheme the caller allows,
 * and must not resolve to a private, loopback, reserved, or link-local address (SSRF guard).
 * Shared by every service that follows a URL somebody else chose.
 */
class SsrfUrlValidator
{
    /**
     * Ranges PHP's own filters let through, but that a server must not be talked into reaching.
     */
    private const RESERVED_IPV4 = [
        '100.64.0.0/10',
        '192.0.0.0/24',
        '192.88.99.0/24',
        '198.18.0.0/15',
        '224.0.0.0/3',
    ];

    /**
     * Resolves the host once and returns a Symfony HttpClient `resolve` map ([host => ip])
     * pinned to the first public address it got; private ones are skipped, so a host on the
     * same machine, which a local resolver also answers with loopback, can still be reached.
     * The caller must pass the map as the `resolve` option, or the client resolves again.
     *
     * @param string[] $schemes the schemes the caller accepts; plain http only where the
     *                          content itself is public anyway, such as a syndicated feed
     *
     * @return array<string,string> suitable for the `resolve` request option
     */
    public function resolveSafe(string $url, array $schemes = ['https']): array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \Exception("Invalid URL '{$url}'");
        }
        if (!in_array(strtolower($parts['scheme']), $schemes, true)) {
            throw new \Exception("URL '{$url}' must use " . implode(' or ', array_map('strtoupper', $schemes)));
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->lookup($host);
        if (empty($ips)) {
            throw new \Exception("Cannot resolve hostname of URL '{$url}'");
        }

        $public = array_values(array_filter($ips, fn (string $ip) => $this->isPublic($ip)));
        if (empty($public)) {
            throw new \Exception("URL '{$url}' resolves to a private or reserved address");
        }

        return [$host => $public[0]];
    }

    /**
     * Every IPv4 and IPv6 address the host resolves to.
     *
     * @return string[]
     */
    protected function lookup(string $host): array
    {
        $ips = [];
        $ipv4 = gethostbyname($host);
        if ($ipv4 !== $host) {
            $ips[] = $ipv4;
        }
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (!empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }

    /**
     * Whether the wiki may connect to this address on somebody else's behalf.
     */
    private function isPublic(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            || $this->isReservedIPv4($ip)) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && stripos($ip, 'fe80') === 0) {
            return false;
        }
        $embeddedIPv4 = $this->extractEmbeddedIPv4($ip);

        return $embeddedIPv4 === null
            || (filter_var($embeddedIPv4, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                && !$this->isReservedIPv4($embeddedIPv4));
    }

    /**
     * The same check, expressed as the curl options that tie a request to the checked address.
     *
     * Redirects are left to the caller: curl cannot run this check again on a new hop, so a
     * caller that follows one must validate it itself.
     *
     * @param string[] $schemes
     *
     * @return array<int,mixed>
     */
    public function curlPin(string $url, array $schemes = ['https']): array
    {
        $resolved = $this->resolveSafe($url, $schemes);
        $host = array_key_first($resolved);
        $parts = parse_url($url);
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        return [
            CURLOPT_RESOLVE => ["{$host}:{$port}:{$resolved[$host]}"],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
    }

    /**
     * Whether the address sits in one of the special-use ranges PHP's filters do not cover.
     */
    private function isReservedIPv4(string $ip): bool
    {
        $address = ip2long($ip);
        if ($address === false) {
            return false;
        }
        foreach (self::RESERVED_IPV4 as $range) {
            [$network, $bits] = explode('/', $range);
            $mask = 0xFFFFFFFF << (32 - (int)$bits) & 0xFFFFFFFF;
            if ((ip2long($network) & $mask) === ($address & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unwraps the IPv4 address embedded in a 6to4 (2002::/16), NAT64 (64:ff9b::/96),
     * or IPv4-compatible (::a.b.c.d) IPv6 literal. Returns null for any other address,
     * including plain global IPv6 and IPv4-mapped (::ffff:a.b.c.d, already caught upstream).
     */
    private function extractEmbeddedIPv4(string $ip): ?string
    {
        $binary = @inet_pton($ip);
        if ($binary === false || strlen($binary) !== 16) {
            return null;
        }
        $bytes = array_values(unpack('C16', $binary));

        if ($bytes[0] === 0x20 && $bytes[1] === 0x02) {
            return sprintf('%d.%d.%d.%d', $bytes[2], $bytes[3], $bytes[4], $bytes[5]);
        }

        if ($bytes[0] === 0x00 && $bytes[1] === 0x64 && $bytes[2] === 0xFF && $bytes[3] === 0x9B
            && array_sum(array_slice($bytes, 4, 8)) === 0) {
            return sprintf('%d.%d.%d.%d', $bytes[12], $bytes[13], $bytes[14], $bytes[15]);
        }

        if (array_sum(array_slice($bytes, 0, 12)) === 0) {
            return sprintf('%d.%d.%d.%d', $bytes[12], $bytes[13], $bytes[14], $bytes[15]);
        }

        return null;
    }
}
