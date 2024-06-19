<?php

// Which is the max number of comments to be shown ?
if ($max = $this->GetParameter('max')) {
    if ($max == 'last') {
        $max = 50;
    } else {
        $last = (int)$max;
    }
} else {
    $max = 50;
}

// Show recent comments
if ($comments = $this->LoadRecentComments($max)) {
    $curday = '';
    foreach ($comments as $comment) {
        // day header
        list($day, $time) = explode(' ', $comment['time']);
        if ($day != $curday) {
            if ($curday) {
                echo "<br />\n";
            }
            echo "<b>$day:</b><br />\n";
            $curday = $day;
        }

        // echo entry
        echo '&nbsp;&nbsp;&nbsp;(',$comment['time'],') <a href="',$this->href('', $comment['comment_on'], 'show_comments=1'),'#',$comment['tag'],'">',$comment['comment_on'],'</a> . . . . ',$this->Format($comment['user']),"<br />\n";
    }
} else {
    echo '<i>' . _t('NO_RECENT_COMMENTS') . '.</i>';
}
