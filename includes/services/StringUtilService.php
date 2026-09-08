<?php

namespace YesWiki\Core\Service;

class StringUtilService
{
    /**
     * Percent-encode every byte outside printable ASCII, which is what a browser puts on
     * the wire, so an address carrying accents or emoji keeps its structure.
     */
    public static function encodeUrlNonAscii(string $url): string
    {
        if (!preg_match('/[^\x20-\x7E]/', $url)) {
            return $url;
        }

        return preg_replace_callback('/[^\x20-\x7E]/', function ($match) {
            return rawurlencode($match[0]);
        }, self::encodeUrlHost($url));
    }

    /**
     * A host carrying accents is spelled in punycode, not percent-encoded, so it has to be
     * converted before the rest of the address is encoded byte by byte.
     */
    private static function encodeUrlHost(string $url): string
    {
        if (!function_exists('idn_to_ascii')) {
            return $url;
        }

        return preg_replace_callback(
            '#^([a-z][a-z0-9+.-]*://(?:[^/?\#@]*@)?)([^/?\#:]+)#i',
            function ($match) {
                $host = idn_to_ascii($match[2], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

                return $match[1] . ($host === false ? $match[2] : $host);
            },
            $url,
            1
        ) ?? $url;
    }

    /**
     * Judge an address on its structure alone, since FILTER_VALIDATE_URL refuses accents
     * and emoji that browsers and sites like LinkedIn accept.
     */
    public static function isWebAddress(string $value): bool
    {
        return filter_var(self::encodeUrlNonAscii($value), FILTER_VALIDATE_URL) !== false;
    }

    public static function folderToNamespace(string $folder): string
    {
        if (preg_match_all('/[a-zA-Z0-9]+/', $folder, $matches) === false) {
            return '';
        }

        return implode('', array_map(function ($input) {return ucfirst(strtolower($input)); }, $matches[0]));
    }
}
