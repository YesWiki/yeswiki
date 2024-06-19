<?php

use YesWiki\Bazar\Service\EntryManager;

if (!function_exists('rssdiff')) {
    function rssdiff($tag, $idfirst, $idlast)
    {
        $output = '';
        global $wiki;
        // TODO : cache ?

        if ($idfirst == $idlast) {
            $previousdiff = $wiki->LoadSingle(
                'select id from '
                . $wiki->config['table_prefix']
                . "pages where tag = '"
                . mysqli_real_escape_string($wiki->dblink, $tag)
                . "' and id < $idfirst order by time desc limit 1"
            );
            if ($previousdiff) {
                $idlast = $previousdiff['id'];
            } else {
                return;
            }
        }

        $pageA = $wiki->LoadPageById($idfirst);
        $pageB = $wiki->LoadPageById($idlast);

        $entryManager = $wiki->services->get(EntryManager::class);
        if ($entryManager->isEntry($tag)) {
            $bodyA = explode(',"', $pageA['body']);
            $bodyB = explode(',"', $pageB['body']);
        } else {
            $bodyA = explode("\n", $pageA['body']);
            $bodyB = explode("\n", $pageB['body']);
        }

        $added = array_diff($bodyA, $bodyB);
        $deleted = array_diff($bodyB, $bodyA);

        if (!isset($output)) {
            $output = '';
        }

        $output .= "<br />\n";
        $output .= "<br />\n";
        $output .= '<b>' . _t('RSS_COMPARISON_OF') . ' <a href="'
            . $wiki->href('', $tag, 'time='
            . urlencode($pageA['time']))
            . '">' . $pageA['time']
            . '</a> ' . _t('RSS_TO') . ' <a href="'
            . $wiki->href('', $tag, 'time=' . urlencode($pageB['time']))
            . '">'
            . $pageB['time']
            . "</a></b><br />\n";

        $wiki->RegisterInclusion($tag);
        if ($added) {
            // remove blank lines
            $output .= "<br />\n<b>" . _t('RSS_ADDS') . ":</b><br />\n";
            $output .= '<div class="additions">' . (implode("\n", $added)) . '</div>';
        }

        if ($deleted) {
            $output .= "<br />\n<b>" . _t('RSS_DELETIONS') . ":</b><br />\n";
            $output .= '<div class="deletions">' . (implode("\n", $deleted)) . '</div>';
        }

        $wiki->UnregisterLastInclusion();

        if (!$added && !$deleted) {
            $output .= "<br />\n" . _t('RSS_NO_DIFF') . '.';
        }

        return $output;
    }
}
