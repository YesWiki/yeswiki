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
$icon = trim($this->GetParameter('icon'));
if (!empty($icon)) {
	// si le parametre contient des espaces, il s'agit d'une icone autre que celles par defaut de bootstrap
	if ( preg_match('/\s/', $icon) )
	{
	   $icon = '<i class="'.$icon.'"></i>';
	}
	else
	{
		$icon = '<i class="icon-'.$icon.' glyphicon glyphicon-'.$icon.'"></i>';
	}
	if (!empty($text)) $icon = $icon.' ';
}

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
	echo '<a href="'.$link.'" class="'.$class.'"'.(!empty($title) ? ' title="'.htmlentities($title, ENT_COMPAT,YW_CHARSET).'"' : (!empty($text) ? ' title="'.htmlentities($text, ENT_COMPAT,YW_CHARSET).'"' : '') ).'>'.$icon.(!empty($text)? htmlentities($text, ENT_COMPAT,YW_CHARSET) : '').'</a>'."\n";
}
?>
