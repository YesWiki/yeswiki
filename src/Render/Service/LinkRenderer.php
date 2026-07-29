<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\LinkTracker;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * HTML link building for wiki content (historic Wiki::LinkTo()): wiki short links,
 * missing pages turned into edit links carrying the current theme, external URLs,
 * mailto, modalbox iframes, and link tracking.
 */
class LinkRenderer
{
    protected UrlFormatter $urlFormatter;
    protected PageManager $pageManager;
    protected LinkTracker $linkTracker;
    protected PageContext $pageContext;
    protected ParameterBagInterface $params;

    public function __construct(
        UrlFormatter $urlFormatter,
        PageManager $pageManager,
        LinkTracker $linkTracker,
        PageContext $pageContext,
        ParameterBagInterface $params
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->pageManager = $pageManager;
        $this->linkTracker = $linkTracker;
        $this->pageContext = $pageContext;
        $this->params = $params;
    }

    /**
     * Create an HTML link.
     *
     * linkTo("WikiPage")
     * linkTo("WikiPage", "My page", ["track" => false])
     * linkTo("WikiPage", "", ["method" => "xml"])
     * linkTo("WikiPage/edit?params=2", "ma page")
     * linkTo("https://test.fr", "mon lien", ["class" => "yeah"])
     *
     * @param string       $link    URL, or wiki tag / short link
     * @param string       $text
     * @param array<mixed> $options HTML attributes, plus 'track', 'method', 'params' and 'html'
     *                              (when truthy, $text is already-safe HTML and is not escaped)
     *
     * @return string HTML link
     */
    public function linkTo($link, $text = '', $options = []): string
    {
        if (!$text) {
            $text = $link;
        }

        // when true, $text is already-safe HTML (e.g. rendered Markdown) and must not be escaped
        $textIsHtml = !empty($options['html']);
        unset($options['html']);

        // YesWiki pages links, like "HomePage" or "HomePage/xml"
        if ($wikiLink = $this->urlFormatter->extractLinkParts($link)) {
            $tag = $wikiLink['tag'];
            $method = $options['method'] ?? $wikiLink['method'];
            $params = $options['params'] ?? $wikiLink['params'];

            // Handle missing Tag
            if ((empty($method) || $method == 'show') && !$this->pageManager->getOne((string)$tag)) {
                $params = array_merge($params, $this->paramsForNewPageLink());
                $method = 'edit';
                $options['data-missing-tag'] = true;
            }

            // Tag and Method to be kept as HTML attributes
            $options['data-tag'] = $tag;
            $options['data-method'] = $method ?? 'show';
            unset($options['method']);
            unset($options['params']);

            // Trackable
            if (!empty($options['track'])) {
                $this->linkTracker->add(explode('?', (string)$tag)[0]);
                $options['data-tracked'] = true;
            }
            unset($options['track']);

            // General URL
            $link = $this->urlFormatter->href($method, $tag, $params, false);
        } elseif ((!isset($options['data-iframe'])
                || strval($options['data-iframe']) != '0')
            && !empty($options['class'])
            && is_string($options['class'])
            && preg_match('/(^|\s)modalbox($|\s)/', $options['class'])
        ) {
            // use iframe for external links in modalbox except if `data-iframe=0`
            $options['data-iframe'] = '1';
            if (!isset($options['title']) && !empty($text)) {
                // set a title because it is beautiful
                $options['title'] = htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);
            }
        }

        // Email addresses
        if (preg_match("/^[\w.-]+\@[\w.-]+$/", $link)) {
            $link = 'mailto:' . $link;
        }

        // Options to HTML attributes
        $stringAttrs = implode(
            ' ',
            array_map(
                function ($key) use ($options) {
                    $value = $options[$key];
                    $encodedValue = is_string($value)
                        ? $value
                        : json_encode($value);

                    return "$key=\"$encodedValue\"";
                },
                array_keys($options)
            )
        );

        // Block script schemes (see RFC 3986 about schemes)
        $link = htmlspecialchars($link, ENT_COMPAT, YW_CHARSET);
        $text = $textIsHtml ? $text : htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);

        // Generate HTML
        return <<<HTML
        <a href="$link" $stringAttrs>$text</a>
        HTML;
    }

    /**
     * Query parameters a "create this missing page" edit link must carry so the new
     * page inherits the current theme/squelette/style (historic ParamsForNewPageLink()).
     *
     * @return array<mixed>
     */
    public function paramsForNewPageLink(): array
    {
        $result = ['newpage' => 1];

        // Config from current page
        $fromConfig = [
            'theme' => 'favorite_theme',
            'squelette' => 'favorite_squelette',
            'style' => 'favorite_style',
            'bgimg' => 'favorite_background_image',
        ];
        foreach ($fromConfig as $param => $configKey) {
            $value = $this->params->has($configKey) ? $this->params->get($configKey) : null;
            if (!empty($value)) {
                $result[$param] = $value;
            }
        }

        // Metadata from current page
        $currentPageTag = $this->pageContext->getTag();
        $pageMetadatas = empty($currentPageTag) ? [] : $this->pageManager->getMetadata($currentPageTag);
        foreach (ThemeManager::SPECIAL_METADATA as $metadata) {
            if (!empty($pageMetadatas[$metadata])) {
                $result[$metadata] = $pageMetadatas[$metadata];
            }
        }

        return $result;
    }
}
