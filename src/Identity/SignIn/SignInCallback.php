<?php

namespace YesWiki\Identity\SignIn;

use YesWiki\Kernel\Service\UrlFormatter;

/** The address a provider sends the person back to, the one built for every type: OIDC's redirect_uri, SAML's ACS URL, CAS's service. */
final class SignInCallback
{
    /** The pair that names the callback again after its key. */
    public const PARAMETER = 'sso_callback';

    /** The callback's key as a server may send it back in the query. */
    private const KEY = '#^(?i:user)/sign-in/[^/&?=]+/callback=?$#D';

    public static function url(UrlFormatter $urls, string $providerId): string
    {
        return $urls->href('', 'user/sign-in/' . rawurlencode($providerId) . '/callback', [self::PARAMETER => $providerId], false);
    }

    /**
     * The address YesWikiInit routes instead of $relativeUri, or null to leave it alone.
     *
     * @param string $relativeUri   the request URI, without the script's location
     * @param mixed  $wikiParameter the `wiki` query parameter
     */
    public static function routeIn(string $relativeUri, mixed $wikiParameter): ?string
    {
        if (!empty($wikiParameter) || !str_starts_with($relativeUri, '/?')) {
            return null;
        }
        $pieces = explode('&', substr($relativeUri, 2));
        if (preg_match('/^[^=]+=./', $pieces[0]) !== 1) {
            return null;
        }
        $key = self::keyAfterTheAnswer($pieces) ?? self::keyNamedByItsPair($pieces);

        return $key === null ? null : '/?' . $key;
    }

    /**
     * The callback's key, when the server rebuilt the query and put its answer first.
     *
     * @param list<string> $pieces the query split on `&`
     */
    private static function keyAfterTheAnswer(array $pieces): ?string
    {
        $keys = array_values(array_filter(
            array_slice($pieces, 1),
            fn (string $piece): bool => preg_match(self::KEY, rawurldecode($piece)) === 1
        ));

        return count($keys) === 1 ? $keys[0] : null;
    }

    /**
     * The callback's key, when the server dropped it but kept the `sso_callback=<id>` pair.
     *
     * @param list<string> $pieces the query split on `&`
     */
    private static function keyNamedByItsPair(array $pieces): ?string
    {
        $ids = [];
        foreach ($pieces as $piece) {
            $pair = explode('=', $piece, 2);
            if (urldecode($pair[0]) === self::PARAMETER) {
                $ids[] = urldecode($pair[1] ?? '');
            }
        }

        return count($ids) === 1 && preg_match('#^[^/&?=]+$#D', $ids[0]) === 1 ? 'user/sign-in/' . rawurlencode($ids[0]) . '/callback' : null;
    }
}
