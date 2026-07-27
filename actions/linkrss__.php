<?php

use YesWiki\Core\Service\FormManager;

// relocated from tools/bazar/actions/linkrss__.php (ticket 24)
$forms = $this->services->get(FormManager::class)->getAll();
$liste = '';

if ($this->CheckModuleACL('rss', 'handler')) {
    if (is_array($forms) && count($forms) > 0) {
        foreach ($forms as $form) {
            $liste .= '  <link rel="alternate" type="application/rss+xml" '
                . 'title="' . htmlspecialchars($form['label'] ?? '') . '" '
                . 'href="' . $this->href('rss', $this->getPageTag(), 'id=' . $form['id']) . '">' . "\n";
        }
    }

    echo '  <link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars(_t('BAZ_FLUX_RSS_GENERAL')) . '" '
        . 'href="' . $this->href('rss') . '">' . "\n" . $liste;
}
