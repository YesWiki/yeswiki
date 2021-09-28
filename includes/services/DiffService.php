<?php

namespace YesWiki\Core\Service;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Wiki;
use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\HtmlDiffConfig;

class DiffService
{
    public function __construct(Wiki $wiki, PageManager $pageManager, EntryManager $entryManager, 
                                EntryController $entryController)
    {
        $this->wiki = $wiki;
        $this->pageManager = $pageManager;
        $this->entryManager = $entryManager;
        $this->entryController = $entryController;
    }

    function getPageDiff($pageA, $pageB, $compareRender = false)
    {
        $tag = $pageA['tag'];
        $isEntry = !empty($tag) && $this->entryManager->isEntry($tag);
        if ($isEntry) {
            if ($compareRender) {
                $textA = $this->entryController->view($tag, $pageA['time'], false);
                $textB = $this->entryController->view($tag, $pageB['time'], false);
            } else {
                $textA = $pageA['body'];
                $textB = $pageB['body'];
            }
        } else {
            if ($compareRender) {
                $textA = $this->formatPageWithOnlySimpleActions($pageA);
                $textB = $this->formatPageWithOnlySimpleActions($pageB);
            } else {
                $textA = _convert($pageA["body"], "ISO-8859-15");
                $textB = _convert($pageB["body"], "ISO-8859-15");
            }
        }

        $config = new HtmlDiffConfig();
        $config->setKeepNewLines(true);
        $config->setIsolatedDiffTags([]);
        $firstHtmlDiff = HtmlDiff::create($textA, $textB, $config);
        return $firstHtmlDiff->build();
    }

    private function formatPageWithOnlySimpleActions($page)
    {
        $actionsToKeep = [
            "grid", "section", "col", "button", "configuration", "end", "label", "nav", "panel", 
            "progressbar", "accordion", "currentpage", "titrepage", "valeur", "lang", "tocjs"
        ];
        $regexpr = "/(\{\{";
        foreach($actionsToKeep as $action) $regexpr .= "(?!$action)";
        $regexpr .= ".*?\}\})/s";
        // move all complex actions (bazarliste etc...) into pre html so they are not fomatted
        $code = preg_replace($regexpr, '""<pre class="ignored-action">$1</pre>""', $page["body"]);
        
        return $this->wiki->Format($code, 'wakka', $page['tag']);
    }
}