<?php

// Which is the max number of pages to be shown ?
if ($max = $this->GetParameter('max')) {
    if ($max == 'last') {
        $max = 50;
    } else {
        $last = (int)$max;
    }
} else {
    $max = 50;
}

// Show recently commented pages
if ($pages = $this->LoadRecentlyCommented($max)) {
    if ($this->GetParameter('max')) {
        foreach ($pages as $page) {
            // echo entry
            echo '(',$page['comment_time'],') <a href="',$this->href('', $page['tag'], 'show_comments=1'),'#',$page['comment_tag'],'">',$page['tag'],'</a> . . . . ' . _t('LAST_COMMENT') . ' ' . _t('BY') . ' ',$this->Format($page['comment_user']),"<br />\n";
        }
    } else {
        $curday = '';
        foreach ($pages as $page) {
            // day header
            list($day, $time) = explode(' ', $page['comment_time']);
            if ($day != $curday) {
                if ($curday) {
                    echo "<br />\n";
                }
                echo "<b>$day&nbsp;:</b><br />\n";
                $curday = $day;
            }

            // echo entry
            echo '&nbsp;&nbsp;&nbsp;(',$time,') <a href="',$this->href('', $page['tag'], 'show_comments=1'),'#',$page['comment_tag'],'">',$page['tag'],'</a> . . . . ' . _t('LAST_COMMENT') . ' ' . _t('BY') . ' ',$this->Format($page['comment_user']),"<br />\n";
        }
    }
} else {
    echo '<i>' . _t('NO_RECENT_COMMENTS_ON_PAGES') . '.</i>';
}
