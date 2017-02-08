<?php
if (!defined("WIKINI_VERSION"))
{
    die ("acc&egrave;s direct interdit");
}


// classe css supplémentaire
$elem = $this->GetParameter('elem');
if (empty($elem)) {
	echo '<div class="alert alert-danger"><strong>'._t('TEMPLATE_ACTION_END').'</strong> : '._t('TEMPLATE_ELEM_PARAMETER_REQUIRED').'.</div>'."\n";
	return;
}
else {
	$pagetag = $this->GetPageTag();
	// teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
	if (!isset($GLOBALS['check_'.$pagetag]['col'])) {
		$GLOBALS['check_'.$pagetag ][$elem] = check_graphical_elements($elem, $pagetag, $this->page['body']);
	}

    if (!isset($GLOBALS['check_'.$pagetag]['panel'])) {
		$GLOBALS['check_'.$pagetag ][$elem] = check_graphical_elements($elem, $pagetag, $this->page['body']);
	}

    if (!isset($GLOBALS['check_'.$pagetag]['accordion'])) {
		$GLOBALS['check_'.$pagetag ][$elem] = check_graphical_elements($elem, $pagetag, $this->page['body']);
	}


	if ($GLOBALS['check_'.$pagetag][$elem]) {
		switch ($elem) {
		    case 'grid':
		        echo "\n</div> <!-- end of grid -->\n";
		        break;
		    case 'col':
		        echo "\n</div> <!-- end of col -->\n";
		        break;
            case 'accordion':
                echo "\n</div> <!-- end of accordion -->\n";
                unset($GLOBALS['check_'.$pagetag ]['accordion_uniqueID']);
                break;
            case 'panel':
                echo "\t\t\n</div>\t\n</div>\n</div> <!-- end of panel -->\n";
                break;
		    case 'buttondropdown':
		        echo "\n</div> <!-- end of buttondropdown -->\n";
		        break;
		    default:
		       break;
		}
	}
}
