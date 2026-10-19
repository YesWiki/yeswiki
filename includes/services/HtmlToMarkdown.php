<?php

namespace YesWiki\Core\Service;

/**
 * Turns HTML from another site into the Markdown the wakka formatter reads, with every wiki markup in its text neutralised.
 */
class HtmlToMarkdown
{
    private const BLOCKS = ['p', 'div', 'blockquote', 'section', 'article', 'header', 'footer', 'figure', 'figcaption', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table', 'tr'];
    private const LINK_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Converts an HTML fragment to wakka-compatible Markdown.
     */
    public function convert(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"?><body>' . $html . '</body>', LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '';
        }
        $markdown = $this->children($body, 0);
        $markdown = preg_replace("/[ \t]+\n/", "\n", $markdown);
        $markdown = preg_replace('/^[ \t]++(?!(?:-|\d+\)) )/m', '', $markdown);
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);

        return trim($markdown, "\n");
    }

    private function children(\DOMNode $node, int $listDepth): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->node($child, $listDepth);
        }

        return $out;
    }

    private function node(\DOMNode $node, int $listDepth): string
    {
        if ($node instanceof \DOMText) {
            return $this->escape(preg_replace('/\s+/u', ' ', $node->textContent));
        }
        if (!$node instanceof \DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        switch ($tag) {
            case 'script':
            case 'style':
            case 'template':
                return '';
            case 'br':
                return "\n";
            case 'hr':
                return "\n\n----\n\n";
            case 'strong':
            case 'b':
                return $this->wrap('**', $this->children($node, $listDepth));
            case 'em':
            case 'i':
                return $this->wrap('*', $this->children($node, $listDepth));
            case 'del':
            case 's':
            case 'strike':
                return $this->wrap('~~', $this->children($node, $listDepth));
            case 'code':
                return $node->parentNode && strtolower($node->parentNode->nodeName) === 'pre'
                    ? $this->escape($node->textContent)
                    : $this->wrap('`', str_replace('`', '', $node->textContent));
            case 'a':
                return $this->link($node, $listDepth);
            case 'img':
                return $this->image($node);
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                $title = trim(str_replace("\n", ' ', $this->children($node, $listDepth)));

                return $title === '' ? '' : "\n\n" . str_repeat('#', (int)$tag[1]) . " $title\n\n";
            case 'ul':
            case 'ol':
                return "\n" . $this->listItems($node, $tag === 'ol', $listDepth + 1) . "\n";
            case 'li':
                return $this->children($node, $listDepth);
        }
        $content = $this->children($node, $listDepth);

        return in_array($tag, self::BLOCKS, true) ? "\n\n" . trim($content, "\n") . "\n\n" : $content;
    }

    private function listItems(\DOMElement $list, bool $ordered, int $depth): string
    {
        $out = '';
        $number = 1;
        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }
            $marker = $ordered ? ($number++) . ')' : '-';
            $text = trim(preg_replace("/\n{2,}/", "\n", $this->children($child, $depth)));
            $out .= "\n" . str_repeat('  ', $depth) . "$marker $text";
        }

        return $out;
    }

    private function link(\DOMElement $node, int $listDepth): string
    {
        $href = $this->safeUrl($node->getAttribute('href'));
        if ($href === null) {
            return $this->children($node, $listDepth);
        }
        $text = trim(preg_replace('/\s+/u', ' ', $node->textContent));

        return '[' . strtr($text === '' ? $href : $text, ['[' => '(', ']' => ')']) . '](' . $href . ')';
    }

    private function image(\DOMElement $node): string
    {
        $src = $this->safeUrl($node->getAttribute('src'));
        if ($src === null) {
            return '';
        }

        $alt = trim(preg_replace('/\s+/u', ' ', $node->getAttribute('alt')));

        return '![' . strtr($alt, ['[' => '(', ']' => ')']) . '](' . $src . ')';
    }

    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($url === '' || !in_array($scheme, self::LINK_SCHEMES, true)) {
            return null;
        }

        return str_replace([' ', '(', ')', '"', '<', '>'], ['%20', '%28', '%29', '%22', '%3C', '%3E'], $url);
    }

    private function wrap(string $marker, string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return $content;
        }

        return $marker . $trimmed . $marker;
    }

    /**
     * Encodes every character wakka would read as markup, so remote text can never become a link, an action or a format.
     */
    private function escape(string $text): string
    {
        $text = preg_replace_callback(
            '/[{}\[\]*_~@£\/%"`#=<>|\\\\&!-]/u',
            fn ($match) => '&#' . mb_ord($match[0]) . ';',
            $text
        );

        return preg_replace_callback(
            '/\b(' . WN_WIKI_LINK . ')\b/u',
            fn ($match) => '&#' . ord($match[1][0]) . ';' . substr($match[1], 1),
            $text
        );
    }
}
