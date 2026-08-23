<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Wiki URL generation (historic Wiki::Href() and friends): tag/method/params to a full URL under base_url, short-link parsing, wiki-name testing.
 */
class UrlFormatter
{
    protected ParameterBagInterface $params;
    protected PageContext $pageContext;

    public function __construct(ParameterBagInterface $params, PageContext $pageContext)
    {
        $this->params = $params;
        $this->pageContext = $pageContext;
    }

    /** Every internal link in $body pointed at the iframe handler, unless it opens a new window. */
    public function throughIframeHandler(string $body): string
    {
        $pattern = '~(<a[[:blank:]]*[^>]*[[:blank:]]*href[[:blank:]]*=[[:blank:]]*)(["\'])((?:' . preg_quote($this->rawBaseUrl(), '~') . '|\?))([\w\-_]+)(\/(?:edit|show))?([&#?].*?)?(\2)([^>]*>)~i';

        $NEW_WINDOW_PATTERN = "~^(.*target=[\"']\s*_?blank\s*[\"'].*)|(.*class=[\"'].*?new-window.*?[\"'].*)$~i";

        if (preg_match_all($pattern, $body, $matches)) {
            foreach ($matches[0] as $key => $match) {
                if (!preg_match($NEW_WINDOW_PATTERN, $matches[1][$key]) && !preg_match(
                    $NEW_WINDOW_PATTERN,
                    $matches[8][$key]
                )) {
                    $replacement =
                        $matches[1][$key] .
                        $matches[2][$key] .
                        $matches[3][$key] .
                        $matches[4][$key] .
                        ($matches[5][$key] == '/edit' ? '/editiframe' : '/iframe') .
                        $matches[6][$key] .
                        $matches[7][$key] .
                        $matches[8][$key];
                    $body = str_replace($match, $replacement, $body);
                }
            }
        }

        return $body;
    }

    /** The raw base_url config value ("https://host/?" or ".../index.php?" form). */
    private function rawBaseUrl(): string
    {
        $baseUrl = $this->params->get('base_url');

        return is_scalar($baseUrl) ? (string)$baseUrl : '';
    }

    /** base_url without its index.php/trailing separator part. */
    public function getBaseUrl(): string
    {
        $url = explode('index.php', $this->rawBaseUrl());

        return preg_replace(['/\/\?$/', '/\/$/'], '', $url[0]) ?? '';
    }

    /** Just `PageName[/method]`, defaulting to the current page (historic MiniHref()). */
    public function miniHref(mixed $method = null, mixed $tag = null): string
    {
        if (!$tag = trim((string)$tag)) {
            $tag = $this->pageContext->getTag();
        }

        return $tag . ($method ? '/' . $method : '');
    }

    /**
     * Full URL to a page/method (historic Href()).
     *
     * @param string|array<mixed>|null $params      query parameters, as an array or an
     *                                              already-encoded string
     * @param bool                     $htmlspchars entity-encode the parameter separator
     */
    public function href(mixed $method = null, mixed $tag = null, mixed $params = null, bool $htmlspchars = true): string
    {
        if ($tag == null || !$tag = trim((string)$tag)) {
            $tag = $this->pageContext->getTag();
        }
        $href = $this->rawBaseUrl() . $this->miniHref($method, $tag);
        if ($params) {
            if (is_array($params)) {
                $paramsArray = [];
                foreach ($params as $key => $value) {
                    if (!empty($value) || in_array($value, [0, '0', ''], true)) {
                        $paramsArray[] = "$key=" . urlencode($value);
                    }
                }
                if (count($paramsArray) > 0) {
                    $params = implode($htmlspchars ? '&amp;' : '&', $paramsArray);
                } else {
                    $params = '';
                }
            }
            $href .= ($this->params->get('rewrite_mode') ? '?' : ($htmlspchars ? '&amp;' : '&')) . $params;
        }

        return $href;
    }

    /**
     * Handle a string that could be a valid URL, a yeswiki short link (`Tag/method?x=1`), or anything else (anchor, relative URL): short links are completed into real URLs, everything else passes through (historic generateLink()).
     */
    public function generateLink(mixed $link): ?string
    {
        if (empty($link)) {
            return null;
        }
        $linkParts = $this->extractLinkParts($link);
        if ($linkParts) {
            return $this->href($linkParts['method'], $linkParts['tag'], $linkParts['params']);
        }

        return $link;
    }

    /**
     * Split a `MyTag/method?param1=value1` style short link (historic extractLinkParts()).
     *
     * @return array{tag: string|null, method: string|null, params: array<mixed>}|null null
     *                                                                                 when $link is not a short link
     */
    public function extractLinkParts(mixed $link): ?array
    {
        if (preg_match('/^(' . WN_CAMEL_CASE_EVOLVED . ')(?:\/(' . WN_CAMEL_CASE_EVOLVED . '))?(?:[?&]('
            . RFC3986_URI_CHARS . '))?$/u', $link, $linkParts)) {
            $tag = !empty($linkParts[1]) ? $linkParts[1] : null;
            $method = !empty($linkParts[2]) ? $linkParts[2] : null;
            $paramsStr = !empty($linkParts[3]) ? $linkParts[3] : null;
            $params = [];
            if (is_string($paramsStr)) {
                parse_str($paramsStr, $params);
            }

            return [
                'tag' => $tag,
                'method' => $method,
                'params' => $params,
            ];
        }

        return null;
    }

    public function isWikiName(mixed $text, string $type = WN_CAMEL_CASE_EVOLVED): bool
    {
        return (bool)preg_match('/^' . $type . '$/u', $text);
    }

    /**
     * Whether $url addresses this wiki -- the question to ask before sending a visitor somewhere a *request* asked for.
     */
    public function isInternal(mixed $url): bool
    {
        if (!is_string($url) || trim($url) === '') {
            return false;
        }
        $url = trim($url);

        if (preg_match('#^/[/\\\\]#', $url) === 1) {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }
        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $base = parse_url($this->getBaseUrl());
        if (!is_array($base)) {
            return false;
        }
        $basePath = rtrim((string)($base['path'] ?? ''), '/');

        if (empty($parts['host'])) {
            $path = (string)($parts['path'] ?? '');

            return str_starts_with($path, '/') && $this->isUnder($path, $basePath);
        }

        return strcasecmp($parts['host'], (string)($base['host'] ?? '')) === 0
            && ($parts['port'] ?? null) == ($base['port'] ?? null)
            && $this->isUnder((string)($parts['path'] ?? ''), $basePath);
    }

    /** A path inside $prefix, counting the prefix itself and refusing `/wiki-evil`. */
    private function isUnder(string $path, string $prefix): bool
    {
        if ($prefix === '') {
            return true;
        }

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }
}
