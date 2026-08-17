<?php

namespace YesWiki\Render\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * HTML link building for wiki content (historic Wiki::LinkTo()): wiki short links, missing pages turned into edit links carrying the current theme, external URLs, mailto, modalbox iframes, and link tracking.
 */
class LinkRenderer
{
    protected UrlFormatter $urlFormatter;
    protected PageManager $pageManager;
    protected PageContext $pageContext;
    protected ParameterBagInterface $params;

    public function __construct(
        UrlFormatter $urlFormatter,
        PageManager $pageManager,
        PageContext $pageContext,
        ParameterBagInterface $params
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->pageManager = $pageManager;
        $this->pageContext = $pageContext;
        $this->params = $params;
    }

    /**
     * Create an HTML link.
     *
     * @param string       $link    URL, or wiki tag / short link
     * @param string       $text
     * @param array<mixed> $options HTML attributes, plus 'method', 'params' and 'html'
     *                              (when truthy, $text is already-safe HTML and is not escaped)
     *
     * @return string HTML link
     */
    public function linkTo($link, $text = '', $options = []): string
    {
        if (!$text) {
            $text = $link;
        }

        $textIsHtml = !empty($options['html']);
        unset($options['html']);

        if ($wikiLink = $this->urlFormatter->extractLinkParts($link)) {
            $tag = $wikiLink['tag'];
            $method = $options['method'] ?? $wikiLink['method'];
            $params = $options['params'] ?? $wikiLink['params'];

            if ((empty($method) || $method == 'show') && !$this->pageManager->getOne((string)$tag)) {
                $params = array_merge($params, $this->paramsForNewPageLink());
                $method = 'edit';
                $options['data-missing-tag'] = true;
            }

            $options['data-tag'] = $tag;
            $options['data-method'] = $method ?? 'show';
            unset($options['method']);
            unset($options['params']);

            $link = $this->urlFormatter->href($method, $tag, $params, false);
        } elseif ((!isset($options['data-iframe'])
                || strval($options['data-iframe']) != '0')
            && !empty($options['class'])
            && is_string($options['class'])
            && preg_match('/(^|\s)modalbox($|\s)/', $options['class'])
        ) {
            $options['data-iframe'] = '1';
            if (!isset($options['title']) && !empty($text)) {
                $options['title'] = htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);
            }
        }

        if (preg_match("/^[\w.-]+\@[\w.-]+$/", $link)) {
            $link = 'mailto:' . $link;
        }

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

        $link = htmlspecialchars($link, ENT_COMPAT, YW_CHARSET);
        $text = $textIsHtml ? $text : htmlspecialchars($text, ENT_COMPAT, YW_CHARSET);

        return <<<HTML
        <a href="$link" $stringAttrs>$text</a>
        HTML;
    }

    /** Positional tag/method/text convenience over linkTo() (historic ComposeLinkToPage()). */
    public function linkToPage(mixed $tag, mixed $method = '', mixed $text = ''): string
    {
        return $this->linkTo((string)$tag, (string)$text, ['method' => $method]);
    }

    /** Positional tag/method/params/text convenience over linkTo() (historic Link()). */
    public function link(mixed $tag, mixed $method = null, mixed $params = null, mixed $text = null, bool $forcedLink = false): string
    {
        return $this->linkTo((string)$tag, (string)$text, [
            'method' => $method,
            'params' => $params,
            'class' => $forcedLink ? 'forced-link' : '',
        ]);
    }

    /**
     * Query parameters a "create this missing page" edit link must carry so the new page inherits the current theme/squelette/style (historic ParamsForNewPageLink()).
     *
     * @return array<mixed>
     */
    public function paramsForNewPageLink(): array
    {
        $result = ['newpage' => 1];

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
