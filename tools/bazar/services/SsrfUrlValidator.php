<?php

namespace YesWiki\Bazar\Service;

use Exception;

/**
 * Validates that a URL is safe to fetch server-side: must be HTTPS and must
 * not resolve to a private, loopback, reserved, or link-local address (SSRF guard).
 * Shared by every service that follows attacker-influenced ActivityPub/WebFinger URLs.
 */
class SsrfUrlValidator
{
    /**
     * Resolves the host exactly once, validates every address it returned, and
     * hands back a Symfony HttpClient `resolve` map ([host => ip]) pinning the
     * connection to the very address that was checked - the caller must pass
     * this as the `resolve` request option so the HTTP client can't perform its
     * own, independent DNS lookup at connect time (which would reopen a
     * DNS-rebinding race against this check).
     *
     * @return array<string,string> suitable for the `resolve` request option
     */
    public function resolveSafe(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new Exception("Invalid URL '{$url}'");
        }
        if ($parts['scheme'] !== 'https') {
            throw new Exception("URL '{$url}' must use HTTPS");
        }

        // Strip IPv6 brackets (e.g. [::1] → ::1)
        $host = trim($parts['host'], '[]');

        // Collect all IPs the host resolves to so every address family is checked.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = [];
            $ipv4 = gethostbyname($host);
            if ($ipv4 !== $host) {
                $ips[] = $ipv4;
            }
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
            if (empty($ips)) {
                throw new Exception("Cannot resolve hostname of URL '{$url}'");
            }
        }

        foreach ($ips as $ip) {
            // Rejects 127.x, 10.x, 172.16-31.x, 192.168.x, 169.254.x, 0.x, 240.x, ::1, fc00::/7
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new Exception("URL '{$url}' resolves to a private or reserved address");
            }
            // fe80::/10 (IPv6 link-local) is not blocked by FILTER_FLAG_NO_RES_RANGE in all PHP versions
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && stripos($ip, 'fe80') === 0) {
                throw new Exception("URL '{$url}' resolves to a private or reserved address");
            }
            // IPv6 transition forms (6to4, NAT64, IPv4-compatible) embed an IPv4 address that
            // filter_var() classifies as an ordinary global IPv6 address; unwrap and re-check it.
            $embeddedIPv4 = $this->extractEmbeddedIPv4($ip);
            if ($embeddedIPv4 !== null
                && !filter_var($embeddedIPv4, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new Exception("URL '{$url}' resolves to a private or reserved address");
            }
        }

        return [$host => $ips[0]];
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

        // 6to4: 2002::/16, IPv4 address in the next 32 bits
        if ($bytes[0] === 0x20 && $bytes[1] === 0x02) {
            return sprintf('%d.%d.%d.%d', $bytes[2], $bytes[3], $bytes[4], $bytes[5]);
        }

        // NAT64 well-known prefix 64:ff9b::/96, IPv4 address in the last 32 bits
        if ($bytes[0] === 0x00 && $bytes[1] === 0x64 && $bytes[2] === 0xff && $bytes[3] === 0x9b
            && array_sum(array_slice($bytes, 4, 8)) === 0) {
            return sprintf('%d.%d.%d.%d', $bytes[12], $bytes[13], $bytes[14], $bytes[15]);
        }

        // IPv4-compatible ::a.b.c.d: first 96 bits zero (distinct from ::ffff:a.b.c.d)
        if (array_sum(array_slice($bytes, 0, 12)) === 0) {
            return sprintf('%d.%d.%d.%d', $bytes[12], $bytes[13], $bytes[14], $bytes[15]);
        }

        return null;
    }
}
