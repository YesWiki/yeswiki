<?php
/**
 * Action to add usefull metas to html head
 * 
 * @category Wiki
 * @package  YesWiki
 * @author   2009-2018 Florian Schmitt <mrflos@lilo.org>
 * @license  GNU/GPL version 3
 * @link     https://yeswiki.net
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->GetMethod() != 'show') {
    echo '<meta name="robots" content="noindex, nofollow">'."\n";
} else {
    if (isset($this->config['meta']['robots'])) {
        echo '<meta name="robots" content="'
          .$this->config['meta']['robots'].'">'."\n";
    }
    // canonical url
    $url = $this->href('', $this->getPageTag());
    echo '<link rel="canonical" href="'.$url.'">'."\n";

    // opengraph
    echo "\n".'  <!-- opengraph -->'."\n";
    echo '  <meta property="og:site_name" content="'
      .$this->config['wakka_name'].'" />'."\n";
    $title = htmlspecialchars(getTitleFromBody($this->page), ENT_COMPAT | ENT_HTML5);
    if ($title) {
        echo '  <meta property="og:title" content="'.$title.'" />'."\n";
    }
    $desc = htmlspecialchars(getDescriptionFromBody($this->page), ENT_COMPAT | ENT_HTML5);
    if ($desc) {
        echo '  <meta property="og:description" content="'.$desc.'" />'."\n";
    }
    echo '  <meta property="og:type" content="article" />'."\n";
    echo '  <meta property="og:url" content="'.$url.'" />'."\n";

    // open graph image : recommended sizes for FB
    $w = 1200; // image width
    $h = 630; // image height
    $img = getImageFromBody($this->page, $w, $h);
    if ($img) {
        echo '  <meta property="og:image" content="'.$img.'" />'."\n";
        echo '  <meta property="og:image:width" content="'.$w.'" />'."\n";
        echo '  <meta property="og:image:height" content="'.$h.'" />'."\n";
    }

}
