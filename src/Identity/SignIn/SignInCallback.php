<?php

namespace YesWiki\Identity\SignIn;

use YesWiki\Kernel\Service\UrlFormatter;

/**
 * The address a provider sends the person back to, the one built for every type: OIDC's redirect_uri, SAML's ACS URL, CAS's service.
 * The callback's route, then the pair that names it again for a server that drops a query key without a value (PARAMETER):
 * `https://wiki.example/?user/sign-in/<id>/callback&sso_callback=<id>`.
 */
final class SignInCallback
{
    /**
     * The pair the callback's address carries after its key: it names the callback again for a server that rebuilds the query and
     * drops a key without a value (authentik, LemonLDAP::NG 2.21 and 2.16).
     */
    public const PARAMETER = 'sso_callback';

    /**
     * The callback's key as a server may send it back in the query: the page name in any case, an identifier without the `&`, `?`
     * or `=` that would end the key, and the empty value a form adds.
     */
    private const KEY = '#^(?i:user)/sign-in/[^/&?=]+/callback=?$#D';

    public static function url(UrlFormatter $urls, string $providerId): string
    {
        return $urls->href('', 'user/sign-in/' . rawurlencode($providerId) . '/callback', [self::PARAMETER => $providerId], false);
    }

    /**
     * The address YesWikiInit routes instead of $relativeUri when an identity server sent the callback back without its key first:
     * `/?<the callback's key>`, or null to leave the address alone.
     * Only at the root (`/?…`, not `/index.php?…` nor a path), without a `wiki` parameter, and when the query starts with an answer
     * (`?code=…`): a page named first without a value (`?Page&…`, `?Page=&…`) and an empty first key (`?=x&…`) keep their route.
     * A page given a value (`?Page=x&…`) gives way to the callback: harmless, the callback refuses whatever has no sign-in in progress.
     *
     * @param string $relativeUri the request URI, without the script's location
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
     * The callback's key (percent-encoded or not, without a value), when the server rebuilt the query as a form and put its answer
     * first: Pocket ID sorts the keys (`?code=…&state=…&user%2Fsign-in%2F<id>%2Fcallback=`). RFC 6749 only asks a server to keep
     * the query, not its order.
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
     * The callback's key, when the server dropped it but kept the `sso_callback=<id>` pair (authentik's parse_qs:
     * `?sso_callback=<id>&code=…&state=…`). The query holds that pair once, its identifier with the characters a callback key's may
     * have (no `/`, `&`, `?` or `=`, once decoded).
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
