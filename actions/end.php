<?php

require_once YESWIKI_SOURCE_DIR . '/src/YesWikiPerformable.php';

use YesWiki\Core\Service\Performer;
use YesWiki\Core\Service\TemplateHelperService;

// classe css supplémentaire
$elem = $this->GetParameter('elem');
if (empty($elem)) {
    echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('TEMPLATE_ACTION_END') . '</strong> : ' . _t('TEMPLATE_ELEM_PARAMETER_REQUIRED') . '.</div>' . "\n";

    return;
}
$pagetag = $this->GetPageTag();
$body = isset($this->page['body']) ? $this->page['body'] : '';
// teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
if (!isset($GLOBALS['check_' . $pagetag])) {
    $GLOBALS['check_' . $pagetag] = [];
}
if (!isset($GLOBALS['check_' . $pagetag][$elem])) {
    $GLOBALS['check_' . $pagetag][$elem] = $this->services->get(TemplateHelperService::class)->checkGraphicalElements($elem, $pagetag, $body);
}

if ($GLOBALS['check_' . $pagetag][$elem] || in_array($elem, ['tab', 'tabs'], true)) {
    echo $this->services->get(Performer::class)->run($elem, 'action', [], true);
}
