<?php

if ($pages = $this->LoadWantedPages()) {
    echo "<ul>\n";
    foreach ($pages as $page) {
        echo '	<li>', $page['tag'];
        echo $this->ComposeLinkToPage($page['tag'], 'edit', '?', false);
        echo ' (';
        echo $this->ComposeLinkToPage($page['tag'], 'backlinks', $page['count'], false);
        echo ")</li>\n";
    }
    echo "</ul>\n";
} else {
    echo '<i>' . _t('NO_PAGE_TO_CREATE') . '.</i>';
}
