<?php

namespace YesWiki\Content\Service;

use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\HtmlDiffConfig;
use Psr\Container\ContainerInterface;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Render\Service\MarkdownFormatterService;

class DiffService
{
    protected EntryController $entryController;
    protected EntryManager $entryManager;
    protected PageManager $pageManager;
    protected ContainerInterface $container;

    public function __construct(
        ContainerInterface $container,
        PageManager $pageManager,
        EntryManager $entryManager,
        EntryController $entryController
    ) {
        $this->container = $container;
        $this->pageManager = $pageManager;
        $this->entryManager = $entryManager;
        $this->entryController = $entryController;
    }

    /**
     * @param array<string, mixed> $pageA
     * @param array<string, mixed> $pageB
     */
    public function getPageDiff(array $pageA, array $pageB, bool $compareRender = false): string
    {
        $tag = (string)($pageA['tag'] ?? '');
        $isEntry = !empty($tag) && $this->entryManager->isEntry($tag);
        if ($isEntry) {
            if ($compareRender) {
                $textA = empty($pageA['time']) ? '' : $this->entryController->view($tag, (string)$pageA['time'], false);
                $textB = empty($pageB['time']) ? '' : $this->entryController->view($tag, (string)$pageB['time'], false);
            } else {
                $textA = $this->formatJsonCodeIntoHtmlTable($pageA);
                $textB = $this->formatJsonCodeIntoHtmlTable($pageB);
            }
        } else {
            if ($compareRender) {
                $textA = $this->formatPageWithOnlySimpleActions($pageA);
                $textB = $this->formatPageWithOnlySimpleActions($pageB);
            } else {
                $textA = PageBody::content(self::body($pageA));
                $textB = PageBody::content(self::body($pageB));
            }
        }

        $config = new HtmlDiffConfig();
        $config->setKeepNewLines(true);
        if (!$isEntry) {
            $config->setIsolatedDiffTags([]);
        }
        $firstHtmlDiff = HtmlDiff::create($textA, $textB, $config);

        return $firstHtmlDiff->build();
    }

    /**
     * @param array<string, mixed> $page
     */
    private function formatPageWithOnlySimpleActions(array $page): string
    {
        $actionsToKeep = [
            'grid', 'section', 'col', 'button', 'configuration', 'end', 'label', 'nav', 'panel',
            'accordion', 'currentpage', 'pagetitle', 'value', 'lang',
        ];
        $regexpr = "/(\{\{";
        foreach ($actionsToKeep as $action) {
            $regexpr .= "(?!$action)";
        }
        $regexpr .= ".*?\}\})/s";

        $content = PageBody::content(self::body($page));
        $code = preg_replace($regexpr, '""<pre class="ignored-action">$1</pre>""', $content) ?? $content;

        return $this->container->get(MarkdownFormatterService::class)->format($code);
    }

    /**
     * @param array<string, mixed> $page
     */
    public function formatJsonCodeIntoHtmlTable(array $page): string
    {
        $result = self::body($page);
        ksort($result);
        $html = "<table class='entry-code'><tbody>";
        foreach ($result as $key => $value) {
            $html .= "<tr><td class='key'><pre>$key</pre></td><td><pre>" . (is_scalar($value) ? $value : json_encode($value)) . '</pre></td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return array<string, mixed>
     */
    private static function body(array $page): array
    {
        return is_array($page['body'] ?? null) ? $page['body'] : [];
    }
}
