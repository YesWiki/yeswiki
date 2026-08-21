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

    /** @return string */
    public function run()
    {
        if ($this->getService(PageContext::class)->getMethod() != 'xml') {
            return _t('TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS') . ' : ' .
                $this->getService(LinkRenderer::class)->link($this->getService(PageContext::class)->getTag(), 'xml', null, $this->getService(UrlFormatter::class)->href('xml'));
        }
        $max = 50;
        if ($user = $this->getService(AuthenticationService::class)->getLoggedUser()) {
            $max = $user['changescount'];
        }

        $aclService = $this->getService(AclService::class);
        $pageManager = $this->getService(PageManager::class);

        // A wiki with nothing recently changed still owes its readers a feed: the bare
        // `return;` that used to stand here handed back null, which reached the caller as an
        // empty body under an XML content type -- a parse error in every feed reader. Falling
        // through renders the same feed with no items in it.
        $pagesList = $pageManager->getRecentlyChanged($max) ?? [];
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

            return ($page1['time'] > $page2['time']) ? -1 : 1;
        });

        $pages = array_slice($pages, 0, $max);

        $lang = $this->getRequest()->query->get('lang');
        if (!empty($lang)) {
            $langParam = ['lang' => $lang];
            unset($_GET['lang']);
        } else {
            $langParam = [];
        }
        $link = $this->getService(UrlFormatter::class)->href(false, $this->arguments['link'], $langParam, false);
        $xmlUrl = $this->getService(UrlFormatter::class)->href('xml', '', $langParam, false);
        $configuredName = $this->params->get('yeswiki_name');
        $yeswikiName = htmlspecialchars(
            is_string($configuredName) ? $configuredName : '',
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
                    . ' --- ' . _t('BY') . " $user" . ($readAcl ? $this->revisionDiff($page['tag'], $firstpage['id'], $lastpage['id']) : '<br><div><i>' . _t('RSS_HIDDEN_CONTENT') . '</i></div>')
                );
                $items[] = compact(['tag', 'user', 'formatedDate', 'description', 'itemurl']);
            }
        }

        $version = $this->params->get('yeswiki_version');
        $release = $this->params->get('yeswiki_release');
        $yesWikiRevision = trim((is_string($version) ? $version : '') . ' ' . (is_string($release) ? $release : ''));
        $description = $this->params->has('meta_description') ? $this->params->get('meta_description') : '';
        // was `empty($decription)`, a typo that made this always true, so the configured
        // meta_description never reached the feed and every wiki's RSS described itself by name
        $description = empty($description) ? $yeswikiName : $description;

        return $this->render(
            '@core/rss/recent-changes-rss.twig',
            compact(['xmlUrl', 'yeswikiName', 'link', 'items', 'yesWikiRevision', 'description'])
        );
    }

    /**
     * What changed between two revisions of $tag, as the markup an RSS item carries.
     *
     * Was the global `rssdiff()` in `Content/rss.functions.php`, which reached the container
     * through `$GLOBALS['yeswikiServices']` because a function has no other way to (ticket 50).
     * This action is its only caller.
     */
    private function revisionDiff(string $tag, int|string $idfirst, int|string $idlast): string
    {
        $output = '';

        if ($idfirst == $idlast) {
            $previousdiff = $this->getService(\YesWiki\Kernel\Service\DbService::class)->loadSingle(
                'select id from '
                . $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['table_prefix']
                . 'pages where tag = ? and id < ? order by '
                . $this->getService(\YesWiki\Kernel\Service\DbService::class)->quoteIdentifier('time')
                . ' desc limit 1',
                [$tag, $idfirst]
            );
            if (!$previousdiff) {
                // the first revision a page ever had has nothing to be compared with
                return '';
            }
            $idlast = $previousdiff['id'];
        }

        $pageA = $this->getService(PageManager::class)->getById($idfirst);
        $pageB = $this->getService(PageManager::class)->getById($idlast);
        if ($pageA === null || $pageB === null) {
            // a revision the page no longer has: nothing to compare, and nothing to say
            return '';
        }

        $entryManager = $this->getService(\YesWiki\Content\Service\EntryManager::class);

        if ($entryManager->isEntry($tag)) {
            $toPairs = function (array $body): array {
                $pairs = [];
                foreach ($body as $key => $value) {
                    $pairs[] = json_encode((string)$key, \YesWiki\Content\Entity\PageBody::JSON_FLAGS)
                        . ':' . json_encode($value, \YesWiki\Content\Entity\PageBody::JSON_FLAGS);
                }

                return $pairs;
            };
            $bodyA = $toPairs($pageA['body'] ?? []);
            $bodyB = $toPairs($pageB['body'] ?? []);
        } else {
            $bodyA = explode("\n", \YesWiki\Content\Entity\PageBody::content($pageA['body'] ?? []));
            $bodyB = explode("\n", \YesWiki\Content\Entity\PageBody::content($pageB['body'] ?? []));
        }

        $added = array_diff($bodyA, $bodyB);
        $deleted = array_diff($bodyB, $bodyA);

        $output .= "<br />\n";
        $output .= "<br />\n";
        $output .= '<b>' . _t('RSS_COMPARISON_OF') . ' <a href="'
            . $this->getService(UrlFormatter::class)->href('', $tag, 'time='
            . urlencode($pageA['time']))
            . '">' . $pageA['time']
            . '</a> ' . _t('RSS_TO') . ' <a href="'
            . $this->getService(UrlFormatter::class)->href('', $tag, 'time=' . urlencode($pageB['time']))
            . '">'
            . $pageB['time']
            . "</a></b><br />\n";

        $this->getService(\YesWiki\Kernel\Service\InclusionStack::class)->register($tag);
        if ($added) {
            $output .= "<br />\n<b>" . _t('RSS_ADDS') . ":</b><br />\n";
            $output .= '<div class="additions">' . implode("\n", $added) . '</div>';
        }

        if ($deleted) {
            $output .= "<br />\n<b>" . _t('RSS_DELETIONS') . ":</b><br />\n";
            $output .= '<div class="deletions">' . implode("\n", $deleted) . '</div>';
        }

        $this->getService(\YesWiki\Kernel\Service\InclusionStack::class)->unregisterLast();

        if (!$added && !$deleted) {
            $output .= "<br />\n" . _t('RSS_NO_DIFF') . '.';
        }

        return $output;
    }
}
