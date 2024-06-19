<?php

if ($pages = $this->LoadAll('SELECT tag FROM ' . $this->config['table_prefix'] . 'pages WHERE latest = \'Y\' AND comment_on=\'\' ORDER BY tag')) {
    foreach ($pages as $page) {
        // XXX: strtoupper is locale dependent
        $firstChar = strtoupper($page['tag'][0]);
        if (!preg_match('/' . WN_UPPER . '/', $firstChar)) {
            $firstChar = '#';
        }

        if (empty($curChar) || $firstChar != $curChar) {
            if (!empty($curChar)) {
                echo "<br />\n";
            }
            echo "<b>$firstChar</b><br />\n";
            $curChar = $firstChar;
        }

        echo $this->ComposeLinkToPage($page['tag'], '', '', false),"<br />\n";
    }
} else {
    echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
}
