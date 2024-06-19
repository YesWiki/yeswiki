<?php

use YesWiki\Templates\Service\Utils;

/*
 * Action to add usefull metas to html head
 */

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

if ($this->GetMethod() != 'show') {
    echo '<meta name="robots" content="noindex, nofollow">' . "\n";
} else {
    if (isset($this->config['meta']['robots'])) {
        echo '<meta name="robots" content="'
          . $this->config['meta']['robots'] . '">' . "\n";
    }
    // canonical url
    $url = $this->href('', $this->getPageTag());
    echo '<link rel="canonical" href="' . $url . '">' . "\n";

    // opengraph
    echo "\n" . '  <!-- opengraph -->' . "\n";
    echo '  <meta property="og:site_name" content="'
      . $this->config['wakka_name'] . '" />' . "\n";
    $utils = $this->services->get(\YesWiki\Templates\Service\Utils::class);
    $title = $utils->getTitleFromBody($this->page);
    echo '  <meta property="og:title" content="' . (!empty($title) ? $title : $GLOBALS['wiki']->config['wakka_name']) . '" />' . "\n";
    $desc = htmlspecialchars($utils->getDescriptionFromBody($this->page, $title), ENT_COMPAT | ENT_HTML5);
    if ($desc) {
        echo '  <meta property="og:description" content="' . $desc . '" />' . "\n";
    }
    echo '  <meta property="og:type" content="article" />' . "\n";
    echo '  <meta property="og:url" content="' . $url . '" />' . "\n";

    // open graph image : recommended sizes for FB
    $w = 1200; // image width
    $h = 630; // image height
    if (!empty($this->page)) {
        $img = $this->services->get(Utils::class)->getImageFromBody($this->page, strval($w), strval($h));
    }
    if (!empty($img)) {
        echo '  <meta property="og:image" content="' . $img . '" />' . "\n";
        echo '  <meta property="og:image:width" content="' . $w . '" />' . "\n";
        echo '  <meta property="og:image:height" content="' . $h . '" />' . "\n";
    }
}
