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
     * @param string       $link
     * @param string       $text
     * @param array<mixed> $options HTML attributes, plus method, params and html (already-safe HTML, not escaped)
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

    /** @return array<mixed> query of a link creating a missing page: the current page's own theme, header and footer */
    public function paramsForNewPageLink(): array
    {
        $result = ['newpage' => 1];

        $currentPageTag = $this->pageContext->getTag();
        $pageMetadatas = (empty($currentPageTag) ? [] : $this->pageManager->getMetadata($currentPageTag)) ?? [];
        if (!empty($pageMetadatas['theme']) && !empty($pageMetadatas['style']) && !empty($pageMetadatas['squelette'])) {
            foreach (['theme', 'squelette', 'style', 'bgimg'] as $param) {
                if (!empty($pageMetadatas[$param])) {
                    $result[$param] = $pageMetadatas[$param];
                }
            }
        }
        foreach (ThemeManager::SPECIAL_METADATA as $metadata) {
            if (!empty($pageMetadatas[$metadata])) {
                $result[$metadata] = $pageMetadatas[$metadata];
            }
        }

        return $result;
    }
}
