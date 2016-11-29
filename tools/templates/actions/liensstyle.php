<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}


// si l'action propose d'autres css à ajouter, on les ajoute
$othercss = $this->GetParameter('othercss');
if (!empty($othercss)) {
	echo $this->Format('{{linkstyle othercss="'.$othercss.'"}}');
} else {
	echo $this->Format('{{linkstyle}}');
}
