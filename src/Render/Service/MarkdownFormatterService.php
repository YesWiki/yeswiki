<?php

namespace YesWiki\Render\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;
use Psr\Container\ContainerInterface;
use YesWiki\Render\Formatter\ActionExtension;
use YesWiki\Render\Formatter\AlertExtension;
use YesWiki\Render\Formatter\CommentExtension;
use YesWiki\Render\Formatter\ProgressExtension;

/** Renders page content: standard CommonMark/GFM Markdown, plus Twig-like comments ({# ... */
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
        $html = (string)(new MarkdownConverter($this->getEnvironment()))->convert($text);

        return $this->assetScanner->scan($html);
    }

    /** Renders only the {{action ...}} tags found in $text, discarding everything else. */
    public function renderActionsOnly(string $text): string
    {
        $actionRunner = $this->actionRunner;

        return (string)preg_replace_callback(
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
     * Parsing-only environment: same heading configuration as the renderer, but WITHOUT ActionExtension.
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

                'heading_permalink' => [
                    'apply_id_to_heading' => true,
                    'insert' => HeadingPermalinkProcessor::INSERT_NONE,
                    'id_prefix' => 'toc',
                    'fragment_prefix' => 'toc',
                ],

                'footnote' => [
                    'container_add_hr' => true,
                    'ref_class' => 'yw-footnote-ref',
                    'backref_class' => 'yw-footnote-backref',
                    'container_class' => 'yw-footnotes',
                ],
                'disallowed_raw_html' => [
                    'disallowed_tags' => $container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('disallowed_html_tags', [
                        'title', 'textarea', 'style', 'xmp', 'noembed', 'noframes', 'script', 'plaintext',
                    ]),
                ],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());

            $environment->addExtension(new HeadingPermalinkExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());

            $environment->addExtension(new AttributesExtension());
            $environment->addExtension(new CommentExtension());
            $environment->addExtension(new ProgressExtension());

            $environment->addExtension(new FootnoteExtension());

            $environment->addExtension(new AlertExtension());
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
