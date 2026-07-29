<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\EntryManager;

if (!function_exists('rssdiff')) {
    function rssdiff($tag, $idfirst, $idlast)
    {
        $output = '';
        $services = $GLOBALS['yeswikiServices'];
        // TODO : cache ?

        if ($idfirst == $idlast) {
            $previousdiff = $services->get(YesWiki\Kernel\Service\DbService::class)->loadSingle(
                'select id from '
                . $services->get(YesWiki\Kernel\Service\RuntimeConfig::class)['table_prefix']
                . "pages where tag = '"
                . $services->get(YesWiki\Kernel\Service\DbService::class)->escape($tag)
                . "' and id < $idfirst order by time desc limit 1"
            );
            if ($previousdiff) {
                $idlast = $previousdiff['id'];
            } else {
                return;
            }
        }

        $pageA = $services->get(YesWiki\Content\Service\PageManager::class)->getById($idfirst);
        $pageB = $services->get(YesWiki\Content\Service\PageManager::class)->getById($idlast);

        $entryManager = $services->get(EntryManager::class);
        // getById() hands back a decoded body (ticket 09) : an entry is diffed field by
        // field (this used to split the stored JSON on `,"`), a page line by line
        if ($entryManager->isEntry($tag)) {
            $toPairs = function (array $body): array {
                $pairs = [];
                foreach ($body as $key => $value) {
                    $pairs[] = json_encode((string)$key, PageBody::JSON_FLAGS)
                        . ':' . json_encode($value, PageBody::JSON_FLAGS);
                }

                return $pairs;
            };
            $bodyA = $toPairs($pageA['body'] ?? []);
            $bodyB = $toPairs($pageB['body'] ?? []);
        } else {
            $bodyA = explode("\n", PageBody::content($pageA['body'] ?? []));
            $bodyB = explode("\n", PageBody::content($pageB['body'] ?? []));
        }

        $added = array_diff($bodyA, $bodyB);
        $deleted = array_diff($bodyB, $bodyA);

        if (!isset($output)) {
            $output = '';
        }

        $output .= "<br />\n";
        $output .= "<br />\n";
        $output .= '<b>' . _t('RSS_COMPARISON_OF') . ' <a href="'
            . $services->get(YesWiki\Kernel\Service\UrlFormatter::class)->href('', $tag, 'time='
            . urlencode($pageA['time']))
            . '">' . $pageA['time']
            . '</a> ' . _t('RSS_TO') . ' <a href="'
            . $services->get(YesWiki\Kernel\Service\UrlFormatter::class)->href('', $tag, 'time=' . urlencode($pageB['time']))
            . '">'
            . $pageB['time']
            . "</a></b><br />\n";

        $services->get(YesWiki\Kernel\Service\InclusionStack::class)->register($tag);
        if ($added) {
            // remove blank lines
            $output .= "<br />\n<b>" . _t('RSS_ADDS') . ":</b><br />\n";
            $output .= '<div class="additions">' . implode("\n", $added) . '</div>';
        }

        if ($deleted) {
            $output .= "<br />\n<b>" . _t('RSS_DELETIONS') . ":</b><br />\n";
            $output .= '<div class="deletions">' . implode("\n", $deleted) . '</div>';
        }

        $services->get(YesWiki\Kernel\Service\InclusionStack::class)->unregisterLast();

        if (!$added && !$deleted) {
            $output .= "<br />\n" . _t('RSS_NO_DIFF') . '.';
        }

        return $output;
    }
}
