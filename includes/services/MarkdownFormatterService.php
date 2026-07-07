<?php

namespace YesWiki\Core\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use YesWiki\Core\Formatter\ActionExtension;
use YesWiki\Core\Formatter\CommentExtension;
use YesWiki\Wiki;

require_once __DIR__ . '/MarkdownFormatter/ActionExtension.php';
require_once __DIR__ . '/MarkdownFormatter/CommentExtension.php';

/**
 * Renders page content: standard CommonMark/GFM Markdown, plus Twig-like comments
 * ({# ... #}) and the YesWiki action syntax ({{actionname param="one" ...}}, see
 * Wiki::Action()).
 */
class MarkdownFormatterService
{
    private Wiki $wiki;
    private ?MarkdownConverter $converter = null;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
    }

    public function format(string $text): string
    {
        return (string)$this->getConverter()->convert($text);
    }

    /**
     * Renders only the {{action ...}} tags found in $text, discarding everything else.
     * Used where a snippet of text is known to be either an action or nothing at all
     * (e.g. RSS/XML output), rather than full page markdown.
     */
    public function renderActionsOnly(string $text): string
    {
        $wiki = $this->wiki;

        return preg_replace_callback(
            '/\{\{(.*?)\}\}|./s',
            function (array $matches) use ($wiki): string {
                if (!isset($matches[1])) {
                    return '';
                }

                return trim($matches[1]) === '' ? '' : $wiki->Action($matches[1]);
            },
            trim($text)
        );
    }

    private function getConverter(): MarkdownConverter
    {
        if ($this->converter === null) {
            $wiki = $this->wiki;

            $environment = new Environment([
                'html_input' => $wiki->GetConfigValue('allow_raw_html', true) ? 'allow' : 'escape',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            // supports [text](url "title"){.class #id key="value"} on links (and images)
            $environment->addExtension(new AttributesExtension());
            $environment->addExtension(new CommentExtension());
            $environment->addExtension(new ActionExtension(
                function (string $action) use ($wiki): string {
                    return $wiki->Action($action);
                },
                function (string $url, string $html, ?string $title, array $attributes) use ($wiki): string {
                    $options = ['html' => true];
                    if ($title !== null) {
                        $options['title'] = $title;
                    }

                    return $wiki->LinkTo($url, $html, array_merge($options, $attributes));
                }
            ));

            $this->converter = new MarkdownConverter($environment);
        }

        return $this->converter;
    }
}
