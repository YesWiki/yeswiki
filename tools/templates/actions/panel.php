<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// Titre du pannel
$title = $this->GetParameter('title');
if (empty($title)) {
    echo '<div><div><div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_PANEL') . '</strong> : '
        . _t('TEMPLATE_TITLE_PARAMETER_REQUIRED');

    return;
}

// classe css pour la couleur du panel ou autre
$class = $this->GetParameter('class');
if (empty($class)) {
    $class = 'panel-default';
}

// collapsed: initial state is collapsed, and the panel is collapsible
// collapsible: initial state is displayed, and the panel is collapsible
// empty: initial state is displayed, and the panel is not collapsible
$type = $this->GetParameter('type');
if (empty($type)) {
    $type = '';
}
$collapsible = ($type == 'collapsed' || $type == 'collapsible');
$collapsed = ($type == 'collapsed');

// data attributes
$data = $this->services->get(YesWiki\Templates\Service\Utils::class)->getDataParameter();

$pagetag = $this->GetPageTag();

// teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
if (!isset($GLOBALS['check_' . $pagetag])) {
    $GLOBALS['check_' . $pagetag] = [];
}
if (!isset($GLOBALS['check_' . $pagetag]['panel'])) {
    $GLOBALS['check_' . $pagetag]['panel'] = $this->services->get(YesWiki\Templates\Service\Utils::class)->checkGraphicalElements('panel', $pagetag, $this->page['body'] ?? '');
}

if ($GLOBALS['check_' . $pagetag]['panel']) {
    $headingID = uniqid('heading');
    $collapseID = uniqid('collapse');
    if (isset($GLOBALS['check_' . $pagetag]['accordion_uniqueID'])) {
        $accordionID = $GLOBALS['check_' . $pagetag]['accordion_uniqueID'];
        $collapsible = ($type == 'collapsible');
        if ($collapsible && !isset($GLOBALS['check_' . $pagetag]['accordion_collapsible'])) {
            $collapsed = false;
            $GLOBALS['check_' . $pagetag]['accordion_collapsible'] = true;
        } else {
            $collapsed = true;
        }
        $collapsible = true;
    } else {
        $accordionID = '';
    }

    $data = '';
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data .= ' data-' . $key . '="' . $value . '"';
        }
    }

    $headerTagName = $collapsible ? 'button' : 'div';
    $result = '<!-- start of panel -->'
        . "<div class=\"panel $class\" $data>
      <$headerTagName class=\"panel-heading" . ($collapsed ? ' collapsed' : '') . '"';
    if ($collapsible) {
        $result .= " id=\"$headingID\"" . ' data-toggle="collapse"' . (!empty($accordionID) ? " data-parent=\"#$accordionID\"" : '')
            . " href=\"#$collapseID\" aria-expanded=\"" . ($collapsed ? 'false' : 'true') . "\" aria-controls=\"$collapseID\"";
    }
    $result .= ">
          <h4 class=\"panel-title\">
           $title
          </h4>
      </$headerTagName>
      
      <div id=\"$collapseID\"";
    if ($collapsible) {
        $result .= ' class="panel-collapse collapse ' . ($collapsed ? '' : 'in') . '" role="tabpanel"'
            . " aria-labelledby=\"$headingID\"";
    }
    $result .= '>
        <div class="panel-body">';

    echo $result;
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_PANEL') . '</strong> : '
        . _t('TEMPLATE_ELEM_PANEL_NOT_CLOSED') . '.</div>' . "\n";

    return;
}
