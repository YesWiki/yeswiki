<?php
/**
 * Action to add usefull metas to html head
 */

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
    $title = getTitleFromBody($this->page);
    echo '  <meta property="og:title" content="' . (!empty($title) ? $title : $GLOBALS['wiki']->config['wakka_name']) . '" />'."\n";
    $desc = htmlspecialchars(getDescriptionFromBody($this->page, $title), ENT_COMPAT | ENT_HTML5);
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
