<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// taille de la colonne
$size = $this->GetParameter('title');
if (empty($size)) {
    echo '<div><div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_PANEL') . '</strong> : '
        . _t('TEMPLATE_TITLE_PARAMETER_REQUIRED') . '.</div>' . "\n";
    return;
}

// classe css pour la couleur du panel ou autre
$class = $this->GetParameter('class');
if (empty($class)) {
    $class = 'panel-default';
}

// data attributes
$data = getDataParameter();

$pagetag = $this->GetPageTag();

// teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
if (!isset($GLOBALS['check_' . $pagetag]['panel'])) {
    $GLOBALS['check_' . $pagetag]['panel'] = check_graphical_elements('panel', $pagetag, $this->page['body']);
}



if ($GLOBALS['check_' . $pagetag]['panel']) {
    $headingID = uniqid('heading');
    $collapseID = uniqid('collapse');

    if (isset($GLOBALS['check_'.$pagetag ]['accordion_uniqueID'])) {
        $accordionID = $GLOBALS['check_'.$pagetag ]['accordion_uniqueID'];
    }

    $data = "";
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data .= ' data-'.$key.'="'.$value.'"';
        }
    }

    echo "<!-- start of panel -->"
    . "<div class=\"panel $class\"  $data>
      <div class=\"panel-heading\" role=\"tab\" id=\"$headingID\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#$accordionID\" href=\"#$collapseID\" aria-expanded=\"true\" aria-controls=\"$collapseID\">
          <h4 class=\"panel-title\">
           $title
          </h4>
      </div>
      <div id=\"$collapseID\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"$headingID\">
        <div class=\"panel-body\">";
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_PANEL') . '</strong> : '
        . _t('TEMPLATE_ELEM_PANEL_NOT_CLOSED') . '.</div>' . "\n";
    return;
}
