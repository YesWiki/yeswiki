<?php

namespace YesWiki\Rss;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiAction;

class RecentChangesRssAction extends YesWikiAction
{
    public function formatArguments($args)
    {
        return [
            'link' => !empty($args['link']) ? $args['link'] : $this->params->get('root_page'),
        ];
    }

    public function run()
    {
        if ($this->wiki->GetMethod() != 'xml') {
            return _t('TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS') . ' : ' .
                $this->wiki->Link($this->wiki->getPageTag(), 'xml', null, $this->wiki->Href('xml'));
        }
        require_once 'tools/rss/libs/rssdiff.function.php';
        $max = 50;
        if ($user = $this->wiki->GetUser()) {
            $max = $user['changescount'];
        }

        $aclService = $this->getService(AclService::class);
        $pageManager = $this->getService(PageManager::class);

        $pagesList = $pageManager->getRecentlyChanged($max);
        if (empty($pagesList)) {
            return;
        }
        $pages = [];
        foreach ($pagesList as $page) {
            $revisions = $pageManager->getRevisions($page['tag'], $max);
            foreach ($revisions as $revision) {
                $pages[] = $revision + ['tag' => $page['tag']];
            }
        }

        usort($pages, function ($page1, $page2) {
            if ($page1['time'] == $page2['time']) {
                return 0;
            }

            return ($page1['time'] > $page2['time']) ? -1 : 1; // décroissant
        });

        $pages = array_slice($pages, 0, $max);

        // correctly format lang param for xml
        if (!empty($_GET['lang'])) {
            $langParam = ['lang' => $_GET['lang']];
            unset($_GET['lang']);
        } else {
            $langParam = [];
        }
        $link = $this->wiki->Href(false, $this->arguments['link'], $langParam, false);
        $xmlUrl = $this->wiki->Href('xml', '', $langParam, false);
        $wakkaName = htmlspecialchars(
            $this->params->get('wakka_name'),
            ENT_COMPAT,
            YW_CHARSET
        );
        $items = [];
        for ($i = 0; $i < sizeof($pages); $i++) {
            $page = $pages[$i];
            $readAcl = $aclService->hasAccess('read', $page['tag']);
            $firstpage = $page;
            $lastpage = $page;
            $break_on_tag = $page['tag'];
            $break_on_user = $page['user'];

            while (($page['tag'] == $break_on_tag)
                and ($page['user'] == $break_on_user)
                and ($i < sizeof($pages))
            ) {
                $i++;
                $lastpage = $page;
                if ($i < sizeof($pages)) {
                    $page = $pages[$i];
                }
            }

            if ($i < sizeof($pages)) {
                $page = $firstpage;
                $tag = htmlspecialchars($page['tag'], ENT_COMPAT, YW_CHARSET);
                $tag = $readAcl ? $tag : substr($tag, 0, 3) . '___';
                $user = htmlspecialchars($page['user'], ENT_COMPAT, YW_CHARSET);
                $formatedDate = gmdate('D, d M Y H:i:s \G\M\T', strtotime($page['time']));
                $rawTime = htmlspecialchars(
                    rawurlencode($page['time']),
                    ENT_COMPAT,
                    YW_CHARSET
                );
                $itemurl = $this->wiki->href(false, $tag, ['time' => $rawTime] + $langParam);
                $description = htmlspecialchars(
                    _t('RSS_CHANGE_OF') . ' ' . ($readAcl ? $this->wiki->ComposeLinkToPage($page['tag']) : $tag)
                    . ($readAcl ? ' (' . $this->wiki->ComposeLinkToPage($page['tag'], 'revisions', _t('RSS_HISTORY')) . ')' : '')
                    . ' --- ' . _t('BY') . " $user" . ($readAcl ? rssdiff($page['tag'], $firstpage['id'], $lastpage['id']) : '<br><div><i>' . _t('RSS_HIDDEN_CONTENT') . '</i></div>')
                );
                $items[] = compact(['tag', 'user', 'formatedDate', 'description', 'itemurl']);
            }
        }

        $yesWikiRevision = "{$this->params->get('yeswiki_version')} {$this->params->get('yeswiki_release')}";
        $description = $this->params->has('meta_description') ? $this->params->get('meta_description') : '';
        $description = empty($decription) ? $wakkaName : $description;

        return $this->render(
            '@rss/recent-changes-rss.twig',
            compact(['xmlUrl', 'wakkaName', 'link', 'items', 'yesWikiRevision', 'description'])
        );
    }
}
