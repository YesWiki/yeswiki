<?php
/*
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

$html  = '<a href="http://www.facebook.com/sharer.php?u='.urlencode($this->Href()).'&amp;t='.urlencode($this->GetPageTag()).'" title="'.TEMPLATE_SHARE_FACEBOOK.'" class="bouton_share"><img src="tools/templates/presentation/images/facebook.png" width="32" height="32" alt="Facebook" /></a>'."\n";
$html .= '<a href="http://twitter.com/home?status='.urlencode(TEMPLATE_SHARE_MUST_READ.$this->Href()).'" title="'.TEMPLATE_SHARE_TWITTER.'" class="bouton_share"><img src="tools/templates/presentation/images/twitter.png" width="32" height="32" alt="Twitter" /></a>'."\n";
$html .= '<a href="http://www.netvibes.com/share?title='.urlencode($this->GetPageTag()).'&amp;url='.urlencode($this->Href()).'" title="'.TEMPLATE_SHARE_TWITTER.'" class="bouton_share"><img src="tools/templates/presentation/images/netvibes.png" width="32" height="32" alt="Netvibes" /></a>'."\n";
$html .= '<a href="http://del.icio.us/post?url='.urlencode($this->Href()).'&amp;title='.urlencode($this->GetPageTag()).'" title="'.TEMPLATE_SHARE_DELICIOUS.'" class="bouton_share"><img src="tools/templates/presentation/images/delicious.png" width="32" height="32" alt="Delicious" /></a>'."\n";
$html .= '<a href="http://www.google.com/reader/link?title='.urlencode($this->GetPageTag()).'&amp;url='.urlencode($this->Href()).'" title="'.TEMPLATE_SHARE_GOOGLEREADER.'" class="bouton_share"><img src="tools/templates/presentation/images/google.png" width="32" height="32" alt="Google" /></a>'."\n";
$html .= '<a href="'.$this->href("mail").'" title="'.TEMPLATE_SHARE_MAIL.'" class="bouton_share"><img src="tools/templates/presentation/images/email.png" width="32" height="32" alt="email" /></a>'."\n";
$html .= '<br /><br /><br /><br />'."\n";
$html .= '<div class="alert alert-info">'.TEMPLATE_SHARE_INCLUDE_CODE.'</div>'."\n";
$html .= "<pre>\n";
$html .= htmlentities('<iframe class="yeswiki_frame" width="100%" height="700" frameborder="0" src="'.$this->Href('iframe').'"></iframe>')."\n";
$html .= "</pre>\n";

// si l'on est dans une requete ajax, pas besoin de titre, et pas besoin de charger tout le html
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	echo utf8_encode('<div class="page">'."\n".$html."\n".'<div>');
}
else {
	echo $this->Header();
	echo "<div class=\"page\">\n<h2>".TEMPLATE_SEE_SHARING_OPTIONS.' '.$this->GetPageTag()."</h2>\n$html\n<hr class=\"hr_clear\" />\n</div>\n";
	echo $this->Footer();
}

?>
