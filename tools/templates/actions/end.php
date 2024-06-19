<?php

use YesWiki\Templates\Controller\TabsController;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// classe css supplémentaire
$elem = $this->GetParameter('elem');
if (empty($elem)) {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_END') . '</strong> : ' . _t('TEMPLATE_ELEM_PARAMETER_REQUIRED') . '.</div>' . "\n";

    return;
} else {
    $pagetag = $this->GetPageTag();
    $body = isset($this->page['body']) ? $this->page['body'] : '';
    // teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
    if (!isset($GLOBALS['check_' . $pagetag])) {
        $GLOBALS['check_' . $pagetag] = [];
    }
    if (!isset($GLOBALS['check_' . $pagetag][$elem])) {
        $GLOBALS['check_' . $pagetag][$elem] = $this->services->get(\YesWiki\Templates\Service\Utils::class)->checkGraphicalElements($elem, $pagetag, $body);
    }

    if ($GLOBALS['check_' . $pagetag][$elem] || in_array($elem, ['tab', 'tabs'], true)) {
        switch ($elem) {
            case 'grid':
                echo "\n</div> <!-- end of grid -->\n";
                break;
            case 'col':
                echo "\n</div> <!-- end of col -->\n";
                break;
            case 'section':
                echo "\n</div>\n</section> <!-- end of section -->\n";
                break;
            case 'label':
                echo '</span>';
                break;
            case 'accordion':
                echo "\n</div> <!-- end of accordion -->\n";
                unset($GLOBALS['check_' . $pagetag]['accordion_uniqueID']);
                break;
            case 'panel':
                echo "\t\t\n</div>\t\n</div>\n</div> <!-- end of panel -->\n";
                break;
            case 'buttondropdown':
                echo "\n</div> <!-- end of buttondropdown -->\n";
                break;
            case 'tab':
                echo $this->services->get(TabsController::class)->closeATab();
                break;
            case 'tabs':
                echo $this->services->get(TabsController::class)->closeTabs();
                break;
            default:
                break;
        }
    }
}
