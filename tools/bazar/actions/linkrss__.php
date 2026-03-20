<?php

use YesWiki\Core\Service\DbService;

$liste = '';
$db = $GLOBALS['wiki']->services->get(DbService::class);
$resultat = $db->loadAll("SELECT bn_id_nature,bn_label_nature FROM {$db->prefixTable('nature')} WHERE 1");

if ($this->CheckModuleACL('rss', 'handler')) {
    if (is_array($resultat) && count($resultat) > 0) {
        foreach ($resultat as $form) {
            $liste .= '  <link rel="alternate" type="application/rss+xml" '
                . 'title="' . htmlspecialchars($form['bn_label_nature'] ?? '') . '" '
                . 'href="' . $this->href('rss', $this->getPageTag(), 'id=' . $form['bn_id_nature']) . '">' . "\n";
        }
    }

    echo '  <link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars(_t('BAZ_FLUX_RSS_GENERAL')) . '" '
        . 'href="' . $this->href('rss') . '">' . "\n" . $liste;
}
