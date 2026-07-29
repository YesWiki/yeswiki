<?php

namespace YesWiki\Render\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;
use Psr\Container\ContainerInterface;
use YesWiki\Render\Formatter\ActionExtension;
use YesWiki\Render\Formatter\CommentExtension;
use YesWiki\Render\Formatter\ProgressExtension;
use YesWiki\Wiki;

/**
 * Renders page content: standard CommonMark/GFM Markdown, plus Twig-like comments
 * ({# ... #}) and the YesWiki action syntax ({{actionname param="one" ...}}, see
 * Wiki::Action()).
 */
class MarkdownFormatterService
{
    private ContentAssetScanner $assetScanner;
    private ActionRunner $actionRunner;
    private LinkRenderer $linkRenderer;
    private ?EnvironmentInterface $environment = null;
    private ?EnvironmentInterface $structureEnvironment = null;

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container, ContentAssetScanner $assetScanner, ActionRunner $actionRunner, LinkRenderer $linkRenderer)
    {
        $this->container = $container;
        $this->assetScanner = $assetScanner;
        $this->actionRunner = $actionRunner;
        $this->linkRenderer = $linkRenderer;
    }

    public function format(string $text): string
    {
        // a fresh MarkdownConverter (cheap: just a parser/renderer pair wrapping the shared,
        // expensive-to-build Environment) per call, since actions rendered while parsing
        // (e.g. recentchanges/listusers, which format() page excerpts themselves) can
        // re-enter format() - the parser holds per-conversion state that a shared instance
        // would corrupt, breaking the outer, still-in-progress parse
        $html = (string)(new MarkdownConverter($this->getEnvironment()))->convert($text);

        // content can opt into a client-side library by class name; register what it needs.
        // Replaces formatters/wakka__.php, which did this as a Performer after-callback.
        return $this->assetScanner->scan($html);
    }

    /**
     * Renders only the {{action ...}} tags found in $text, discarding everything else.
     * Used where a snippet of text is known to be either an action or nothing at all
     * (e.g. RSS/XML output), rather than full page markdown.
     */
    public function renderActionsOnly(string $text): string
    {
        $actionRunner = $this->actionRunner;

        return preg_replace_callback(
            '/\{\{(.*?)\}\}|./s',
            function (array $matches) use ($actionRunner): string {
                if (!isset($matches[1])) {
                    return '';
                }

                return trim($matches[1]) === '' ? '' : $actionRunner->action($matches[1]);
            },
            trim($text)
        );
    }

    /**
     * The headings of $text with the exact ids the rendered HTML will carry.
     *
     * Both the toc action and the renderer go through this one environment, so the links
     * and the anchors cannot drift apart -- which is precisely what the old two-sided
     * arrangement (a counter in the formatter hook, a matching counter in translate2toc)
     * could not guarantee.
     *
     * @return list<array{level: int, id: string, title: string}>
     */
    public function headings(string $text): array
    {
        $document = (new MarkdownParser($this->getStructureEnvironment()))->parse($text);

        $headings = [];
        foreach ($document->iterator() as $node) {
            if (!$node instanceof Heading) {
                continue;
            }
            // With insert => INSERT_NONE the processor applies the id to the heading and
            // inserts no HeadingPermalink node at all, so the id has to be read off the
            // heading's own attributes rather than from a child inline.
            $id = $node->data->get('attributes')['id'] ?? null;
            if (!is_string($id) || $id === '') {
                continue;
            }
            $title = '';
            foreach ($node->iterator() as $inline) {
                if ($inline instanceof StringContainerInterface) {
                    $title .= $inline->getLiteral();
                } elseif ($inline instanceof Newline) {
                    $title .= ' ';
                }
            }
            $headings[] = ['level' => $node->getLevel(), 'id' => $id, 'title' => trim($title)];
        }

        return $headings;
    }

    /**
     * Parsing-only environment: same heading configuration as the renderer, but WITHOUT
     * ActionExtension.
     *
     * The full environment executes {{action}} tags while parsing. A page containing
     * {{toc}} would therefore run the toc action, which calls headings(), which parses the
     * same body again -- unbounded recursion (the first attempt at this died at ~257k stack
     * frames). The heading ids are unaffected: HeadingPermalink derives them from the
     * heading text alone, and actions never contribute headings to the AST.
     */
    private function getStructureEnvironment(): EnvironmentInterface
    {
        if ($this->structureEnvironment === null) {
            $environment = new Environment([
                'heading_permalink' => [
                    'apply_id_to_heading' => true,
                    'insert' => HeadingPermalinkProcessor::INSERT_NONE,
                    'id_prefix' => 'toc',
                    'fragment_prefix' => 'toc',
                ],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            $environment->addExtension(new HeadingPermalinkExtension());
            $this->structureEnvironment = $environment;
        }

        return $this->structureEnvironment;
    }

    private function getEnvironment(): EnvironmentInterface
    {
        if ($this->environment === null) {
            $container = $this->container;

            $environment = new Environment([
                'html_input' => $container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('allow_raw_html', true) ? 'allow' : 'escape',
                'allow_unsafe_links' => false,
                // GithubFlavoredMarkdownExtension pulls in DisallowedRawHtmlExtension, which by
                // default escapes <iframe> along with <script>/<style>/etc.; YesWiki pages rely
                // on embedding iframes (maps, videos...), so the default list (see
                // yeswiki.config.php's 'disallowed_html_tags') excludes it while keeping the
                // rest of that denylist. Wiki admins can override it in their own config.
                'heading_permalink' => [
                    'apply_id_to_heading' => true,
                    'insert' => HeadingPermalinkProcessor::INSERT_NONE,
                    'id_prefix' => 'toc',
                    'fragment_prefix' => 'toc',
                ],
                'disallowed_raw_html' => [
                    'disallowed_tags' => $container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('disallowed_html_tags', [
                        'title', 'textarea', 'style', 'xmp', 'noembed', 'noframes', 'script', 'plaintext',
                    ]),
                ],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            // Assigns a stable id to every heading, on the AST rather than by regexing the
            // rendered HTML afterwards. Replaces the hand-rolled `TOC_{level}_{n}` pass in
            // formatters/wakka__.php (ticket 06), which counted every <hN> in the final
            // output -- so a heading emitted by another action's own HTML shifted the
            // numbering and silently desynced the toc's links. INSERT_NONE because we want
            // the id only, not the clickable anchor CommonMark adds by default.
            $environment->addExtension(new HeadingPermalinkExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            // supports [text](url "title"){.class #id key="value"} on links (and images)
            $environment->addExtension(new AttributesExtension());
            $environment->addExtension(new CommentExtension());
            $environment->addExtension(new ProgressExtension());
            $actionRunner = $this->actionRunner;
            $linkRenderer = $this->linkRenderer;
            $environment->addExtension(new ActionExtension(
                function (string $action) use ($actionRunner): string {
                    return $actionRunner->action($action);
                },
                function (string $url, string $html, ?string $title, array $attributes) use ($linkRenderer): string {
                    $options = ['html' => true];
                    if ($title !== null) {
                        $options['title'] = $title;
                    }

                    return $linkRenderer->linkTo($url, $html, array_merge($options, $attributes));
                }
            ));

            $this->environment = $environment;
        }

        return $this->environment;
    }
}
