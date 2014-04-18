<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// adresse vers quoi le bouton pointe
$link = $this->GetParameter('link');
if ($this->IsWikiName($link)) {
	$link = $this->href('', $link);
}

// texte genere a l'interieur du bouton
$text = $this->GetParameter('text');

// titre au survol du bouton et dans la boite modale associée
$title = $this->GetParameter('title');

// icone du bouton
$icon = $this->GetParameter('icon');
if (!empty($icon)) $icon = '<i class="icon-'.$icon.' glyphicon glyphicon-'.$icon.'"></i>';

// classe css supplémentaire pour changer le look
$class = $this->GetParameter('class');
$class = 'btn '.$class;
if (!strstr($class, 'btn-')) $class .= ' btn-default';


$nobtn = $this->GetParameter('nobtn');
if (!empty($nobtn) && $nobtn == '1') $class = str_replace(array('btn ', 'btn-default'), array('',''), $class);

if (empty($link)) {
        echo '<div class="alert alert-danger"><strong>'._t('TEMPLATE_ACTION_BUTTON').'</strong> : '._t('TEMPLATE_LINK_PARAMETER_REQUIRED').'.</div>'."\n";
}
else {
	echo '<a href="'.$link.'" class="'.$class.'"'.(!empty($title) ? ' title="'.htmlentities($title, ENT_COMPAT,TEMPLATES_DEFAULT_CHARSET).'"' : (!empty($text) ? ' title="'.htmlentities($text, ENT_COMPAT,TEMPLATES_DEFAULT_CHARSET).'"' : '') ).'>'.$icon.(!empty($text)? '&nbsp;'.htmlentities($text, ENT_COMPAT,TEMPLATES_DEFAULT_CHARSET) : '').'</a>'."\n";
}
?>
