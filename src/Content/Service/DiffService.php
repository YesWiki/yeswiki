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
    protected $entryController;
    protected $entryManager;
    protected $pageManager;
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

    public function getPageDiff($pageA, $pageB, $compareRender = false)
    {
        $tag = $pageA['tag'];
        $isEntry = !empty($tag) && $this->entryManager->isEntry($tag);
        if ($isEntry) {
            if ($compareRender) {
                $textA = $pageA['time'] ? $this->entryController->view($tag, $pageA['time'], false) : '';
                $textB = $pageB['time'] ? $this->entryController->view($tag, $pageB['time'], false) : '';
            } else {
                $textA = $this->formatJsonCodeIntoHtmlTable($pageA);
                $textB = $this->formatJsonCodeIntoHtmlTable($pageB);
            }
        } else {
            if ($compareRender) {
                $textA = $this->formatPageWithOnlySimpleActions($pageA);
                $textB = $this->formatPageWithOnlySimpleActions($pageB);
            } else {
                $textA = PageBody::content($pageA['body'] ?? []);
                $textB = PageBody::content($pageB['body'] ?? []);
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

    private function formatPageWithOnlySimpleActions($page)
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

        $code = preg_replace($regexpr, '""<pre class="ignored-action">$1</pre>""', PageBody::content($page['body'] ?? []));

        return $this->container->get(MarkdownFormatterService::class)->format($code);
    }

    public function formatJsonCodeIntoHtmlTable($page)
    {
        $result = $page['body'] ?? [];
        ksort($result);
        $html = "<table class='entry-code'><tbody>";
        foreach ($result as $key => $value) {
            $html .= "<tr><td class='key'><pre>$key</pre></td><td><pre>" . (is_scalar($value) ? $value : json_encode($value)) . '</pre></td></tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }
}
