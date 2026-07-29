<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;

class RecentChangesRssAction extends YesWikiAction implements RegisteredAction
{
    /** `{{recentchangesrss}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'recentchangesrss';
    }

    public function formatArguments($args)
    {
        return [
            'link' => !empty($args['link']) ? $args['link'] : $this->params->get('root_page'),
        ];
    }

    public function run()
    {
        if ($this->getService(PageContext::class)->getMethod() != 'xml') {
            return _t('TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS') . ' : ' .
                $this->getService(LinkRenderer::class)->link($this->getService(PageContext::class)->getTag(), 'xml', null, $this->getService(UrlFormatter::class)->href('xml'));
        }
        require_once YESWIKI_SOURCE_DIR . '/src/rss.functions.php';
        $max = 50;
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
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
        $lang = $this->getRequest()->query->get('lang');
        if (!empty($lang)) {
            $langParam = ['lang' => $lang];
            unset($_GET['lang']); // prevent wiki->Href() from duplicating the lang param
        } else {
            $langParam = [];
        }
        $link = $this->getService(UrlFormatter::class)->href(false, $this->arguments['link'], $langParam, false);
        $xmlUrl = $this->getService(UrlFormatter::class)->href('xml', '', $langParam, false);
        $yeswikiName = htmlspecialchars(
            $this->params->get('yeswiki_name'),
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
                $itemurl = $this->getService(UrlFormatter::class)->href(false, $tag, ['time' => $rawTime] + $langParam);
                $description = htmlspecialchars(
                    _t('RSS_CHANGE_OF') . ' ' . ($readAcl ? $this->getService(LinkRenderer::class)->linkToPage($page['tag']) : $tag)
                    . ($readAcl ? ' (' . $this->getService(LinkRenderer::class)->linkToPage($page['tag'], 'revisions', _t('RSS_HISTORY')) . ')' : '')
                    . ' --- ' . _t('BY') . " $user" . ($readAcl ? rssdiff($page['tag'], $firstpage['id'], $lastpage['id']) : '<br><div><i>' . _t('RSS_HIDDEN_CONTENT') . '</i></div>')
                );
                $items[] = compact(['tag', 'user', 'formatedDate', 'description', 'itemurl']);
            }
        }

        $yesWikiRevision = "{$this->params->get('yeswiki_version')} {$this->params->get('yeswiki_release')}";
        $description = $this->params->has('meta_description') ? $this->params->get('meta_description') : '';
        $description = empty($decription) ? $yeswikiName : $description;

        return $this->render(
            '@core/rss/recent-changes-rss.twig',
            compact(['xmlUrl', 'yeswikiName', 'link', 'items', 'yesWikiRevision', 'description'])
        );
    }
}
