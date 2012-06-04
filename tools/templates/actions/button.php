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

// icone du bouton
$icon = $this->GetParameter('icon');
if (!empty($icon)) $icon = '<i class="icon-'.$icon.'"></i>';

// classe css supplémentaire pour changer le look
$class = $this->GetParameter('class');
$class = 'btn '.$class;

$nobtn = $this->GetParameter('nobtn');
if (!empty($nobtn) && $nobtn == '1') $class = str_replace('btn ', '', $class);

if (empty($link)) {
        echo '<div class="error">Action button : param&egrave;tre "link" obligatoire.</div>'."\n";
}
else {
	echo '<a href="'.$link.'" class="'.$class.'">'.$icon.(!empty($text)? '&nbsp;'.$text : '').'</a>'."\n";
}
?>
