<?php

if ($page = trim($this->GetParameter('page'))) {
    $title = _t('PAGES_WITH_LINK') . ' ' . $this->ComposeLinkToPage($page) . "&nbsp;: <br />\n";
} else {
    $page = $this->getPageTag();
    $title = _t('PAGES_WITH_LINK_TO_CURRENT_PAGE') . "&nbsp;: <br />\n";
}

$pages = $this->LoadPagesLinkingTo($page);

if ($pages) {
    echo $title;
    $exclude = explode(';', $this->GetParameter('exclude'));
    foreach ($exclude as $key => $exclusion) {
        $exclude[$key] = trim($exclusion);
    }

    foreach ($pages as $page) {
        if (!in_array($page['tag'], $exclude)) {
            echo $this->ComposeLinkToPage($page['tag'], '', '', false), "<br />\n";
        }
    }
} else {
    echo '<i>' . _t('NO_PAGES_WITH_LINK_TO') . ' ', $this->ComposeLinkToPage($page), '.</i>';
}
