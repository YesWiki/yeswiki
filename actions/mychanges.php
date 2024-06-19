<?php

if ($user = $this->GetUser()) {
    $my_edits_count = 0;
    $curChar = '';
    $curday = '';
    $last_tag = '';

    if (($bydate = $this->GetParameter('bydate'))) {
        echo '<b>' . _t('YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE') . ".</b><br /><br />\n";

        if ($pages = $this->LoadAll('SELECT tag, time FROM ' . $this->config['table_prefix'] . "pages WHERE user = '" . mysqli_real_escape_string($this->dblink, $this->GetUserName()) . "' AND tag NOT LIKE 'Comment%' ORDER BY time ASC, tag ASC")) {
            foreach ($pages as $page) {
                $edited_pages[$page['tag']] = $page['time'];
            }

            arsort($edited_pages);

            foreach ($edited_pages as $page['tag'] => $page['time']) {
                // day header
                list($day, $time) = explode(' ', $page['time']);
                if ($day != $curday) {
                    if ($curday) {
                        echo "<br />\n";
                    }
                    echo "<b>$day:</b><br />\n";
                    $curday = $day;
                }

                // echo entry
                echo "&nbsp;&nbsp;&nbsp;($time) (",$this->ComposeLinkToPage($page['tag'], 'revisions', 'history', 0),') ',$this->ComposeLinkToPage($page['tag'], '', '', 0),"<br />\n";

                $my_edits_count++;
            }

            if ($my_edits_count == 0) {
                echo '<i>' . _t('YOU_DIDNT_MODIFY_ANY_PAGE') . '.</i>';
            }
        } else {
            echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
        }
    } else {
        echo '<b>' . _t('YOUR_MODIFIED_PAGES_ORDERED_BY_NAME') . ".</b><br /><br />\n";

        if ($pages = $this->LoadAll('SELECT tag, time FROM ' . $this->config['table_prefix'] . "pages WHERE user = '" . mysqli_real_escape_string($this->dblink, $this->GetUserName()) . "' AND tag NOT LIKE 'Comment%' ORDER BY tag ASC, time DESC")) {
            foreach ($pages as $page) {
                if ($last_tag != $page['tag']) {
                    $last_tag = $page['tag'];
                    // XXX: strtoupper is locale dependent
                    $firstChar = strtoupper($page['tag'][0]);
                    if (!preg_match('/' . WN_UPPER . '/', $firstChar)) {
                        $firstChar = '#';
                    }

                    if ($firstChar != $curChar) {
                        if ($curChar) {
                            echo "<br />\n";
                        }
                        echo "<b>$firstChar</b><br />\n";
                        $curChar = $firstChar;
                    }

                    // echo entry
                    echo '&nbsp;&nbsp;&nbsp;(',$page['time'],') (',$this->ComposeLinkToPage($page['tag'], 'revisions', 'history', 0),') ',$this->ComposeLinkToPage($page['tag'], '', '', 0),"<br />\n";

                    $my_edits_count++;
                }
            }

            if ($my_edits_count == 0) {
                echo '<i>' . _t('YOU_DIDNT_MODIFY_ANY_PAGE') . '.</i>';
            }
        } else {
            echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
        }
    }
} else {
    echo '<div class="alert alert-danger">' . _t('YOU_ARENT_LOGGED_IN') . ' : ' . _t('IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES') . ".</div>\n";
}
