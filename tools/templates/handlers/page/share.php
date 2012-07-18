<?php
/*
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

//include_once 'tools/qrcode/libs/qrlib.php';

$html  = "<div class=\"page\">"."\n";
$html .= "<h2>Partager cette page</h2>\n";
$html .= '<a href="http://www.facebook.com/sharer.php?u='.urlencode($this->Href()).'&amp;t='.urlencode($this->GetPageTag()).'" title="Partager sur Facebook" class="bouton_share"><img src="tools/templates/presentation/images/facebook.png" width="32" height="32" alt="Facebook" /></a>'."\n";
$html .= '<a href="http://twitter.com/home?status='.urlencode('A lire : '.$this->Href()).'" title="Partager sur Twitter" class="bouton_share"><img src="tools/templates/presentation/images/twitter.png" width="32" height="32" alt="Twitter" /></a>'."\n";
$html .= '<a href="http://www.netvibes.com/share?title='.urlencode($this->GetPageTag()).'&amp;url='.urlencode($this->Href()).'" title="Partager sur Netvibes" class="bouton_share"><img src="tools/templates/presentation/images/netvibes.png" width="32" height="32" alt="Netvibes" /></a>'."\n";
$html .= '<a href="http://del.icio.us/post?url='.urlencode($this->Href()).'&amp;title='.urlencode($this->GetPageTag()).'" title="Partager sur Delicious" class="bouton_share"><img src="tools/templates/presentation/images/delicious.png" width="32" height="32" alt="Delicious" /></a>'."\n";
$html .= '<a href="http://www.google.com/reader/link?title='.urlencode($this->GetPageTag()).'&amp;url='.urlencode($this->Href()).'" title="Partager sur Google" class="bouton_share"><img src="tools/templates/presentation/images/google.png" width="32" height="32" alt="Google" /></a>'."\n";
$html .= '<a href="'.$this->href("mail").'" title="Envoyer le contenu de cette page par mail" class="bouton_share"><img src="tools/templates/presentation/images/email.png" width="32" height="32" alt="email" /></a>'."\n";
$html .= '<br /><br /><br /><br />'."\n";
$html .= 'Code d\'int&eacute;gration de contenu dans une page HTML'."\n";
$html .= "<pre>\n";
$html .= htmlentities('<iframe class="yeswiki_frame" width="100%" height="600" frameborder="0" src="'.$this->Href('iframe').'"></iframe>')."\n";
$html .= "</pre>"."\n";

echo utf8_encode($html);
?>
