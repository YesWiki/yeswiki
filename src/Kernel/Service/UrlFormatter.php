<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Wiki URL generation (historic Wiki::Href() and friends): tag/method/params to a full
 * URL under base_url, short-link parsing, wiki-name testing.
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
        if (isset($_GET['lang']) && $_GET['lang'] != '') {
            $href .= '&lang=' . $GLOBALS['prefered_language'];
        }

        return $href;
    }

    /**
     * Handle a string that could be a valid URL, a yeswiki short link (`Tag/method?x=1`),
     * or anything else (anchor, relative URL): short links are completed into real URLs,
     * everything else passes through (historic generateLink()).
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

        // a valid URL, or (tolerantly) a relative URL or an anchor
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
}
