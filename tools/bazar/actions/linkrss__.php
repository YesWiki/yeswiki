<?php


$liste = '';
$resultat = baz_valeurs_formulaire();

if ($this->CheckModuleACL('rss', 'handler')) {
    if (is_array($resultat) && count($resultat) > 0) {
        foreach ($resultat as $form) {
            $liste .= '  <link rel="alternate" type="application/rss+xml" '
                . 'title="' . htmlspecialchars($form['body']['title']) . '" '
                . 'href="' . $this->href('rss', $this->getPageTag(), 'id=' . $form['body']['id']) . '">' . "\n";
        }
    }

    echo '  <link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars(_t('BAZ_FLUX_RSS_GENERAL')) . '" '
        . 'href="' . $this->href('rss') . '">' . "\n" . $liste;
}
