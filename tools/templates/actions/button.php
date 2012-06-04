<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// adresse url vers quoi le bouton pointe
$url = $this->GetParameter('url');

// texte genere a l'interieur du bouton
$text = $this->GetParameter('text');

// icone du bouton
$icon = $this->GetParameter('icon');
if (!empty($icon)) $icon = '<i class="icon-'.$icon.'"></i>&nbsp;';

// classe css supplémentaire pour changer le look
$class = $this->GetParameter('class');
$class = 'btn '.$class;

if (empty($url)) {
        echo '<div class="error">Action button : param&egrave;tre "url" obligatoire.</div>'."\n";
}
elseif (empty($text)) {
        echo '<div class="error">Action button : param&egrave;tre "text" obligatoire.</div>'."\n";
} 
else {
	echo '<a href="'.$url.'" class="'.$class.'">'.$icon.$text.'</a>'."\n";
}
?>
