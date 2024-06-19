<?php

if ($user = $this->GetUser()) {
    echo '<b>' . _t('LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER') . ".</b><br /><br />\n";

    $my_pages_count = 0;
    $curChar = '';

    if ($pages = $this->LoadAllPages()) {
        foreach ($pages as $page) {
            if ($this->GetUserName() == $page['owner'] && !preg_match('/^Comment/', $page['tag'])) {
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

                echo $this->ComposeLinkToPage($page['tag']),"<br />\n";

                $my_pages_count++;
            }
        }

        if ($my_pages_count == 0) {
            echo '<i>' . _t('YOU_DONT_OWN_ANY_PAGE') . '.</i>';
        }
    } else {
        echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
    }
} else {
    echo '<div class="alert alert-danger">' . _t('YOU_ARENT_LOGGED_IN') . ' : ' . _t('IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES') . ".</div>\n";
}
