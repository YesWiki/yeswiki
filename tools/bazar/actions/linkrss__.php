<?php

use YesWiki\Bazar\Service\FormManager;

$liste = '';
$formManager = $GLOBALS['wiki']->services->get(FormManager::class);
$resultat = $formManager->getAllIds();

if ($this->CheckModuleACL('rss', 'handler')) {
    if (is_array($resultat) && count($resultat) > 0) {
        foreach ($resultat as $id => $title) {
            $liste .= '  <link rel="alternate" type="application/rss+xml" '
                . 'title="' . htmlspecialchars($title ?? '') . '" '
                . 'href="' . $this->href('rss', $this->getPageTag(), 'id=' . $id) . '">' . "\n";
        }
    }

    echo '  <link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars(_t('BAZ_FLUX_RSS_GENERAL')) . '" '
        . 'href="' . $this->href('rss') . '">' . "\n" . $liste;
}
