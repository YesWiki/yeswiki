<?php
/*
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$html  = '<a href="http://www.facebook.com/sharer.php?u='.urlencode($this->Href()).'&amp;t='.urlencode($this->GetPageTag()).'" title="'._t('TEMPLATE_SHARE_FACEBOOK').'" class="bouton_share"><img src="tools/templates/presentation/images/facebook.png" width="32" height="32" alt="Facebook" /></a>'."\n";
$html .= '<a href="http://twitter.com/home?status='.urlencode(_t('TEMPLATE_SHARE_MUST_READ').$this->Href()).'" title="'._t('TEMPLATE_SHARE_TWITTER').'" class="bouton_share"><img src="tools/templates/presentation/images/twitter.png" width="32" height="32" alt="Twitter" /></a>'."\n";
$html .= '<a href="http://www.netvibes.com/share?title='.urlencode($this->GetPageTag()).'&amp;url='.urlencode($this->Href()).'" title="'._t('TEMPLATE_SHARE_TWITTER').'" class="bouton_share"><img src="tools/templates/presentation/images/netvibes.png" width="32" height="32" alt="Netvibes" /></a>'."\n";
$html .= '<a href="http://del.icio.us/post?url='.urlencode($this->Href()).'&amp;title='.urlencode($this->GetPageTag()).'" title="'._t('TEMPLATE_SHARE_DELICIOUS').'" class="bouton_share"><img src="tools/templates/presentation/images/delicious.png" width="32" height="32" alt="Delicious" /></a>'."\n";
$html .= '<a href="http://www.google.com/reader/link?title='.urlencode($this->GetPageTag()).'&amp;url='.urlencode($this->Href()).'" title="'._t('TEMPLATE_SHARE_GOOGLEREADER').'" class="bouton_share"><img src="tools/templates/presentation/images/google.png" width="32" height="32" alt="Google" /></a>'."\n";
$html .= '<a href="'.$this->href("mail").'" title="'._t('TEMPLATE_SHARE_MAIL').'" class="bouton_share"><img src="tools/templates/presentation/images/email.png" width="32" height="32" alt="email" /></a>'."\n";
$html .= '<br /><br />'."\n";
$html .= '<div class="alert alert-info">'._t('TEMPLATE_SHARE_INCLUDE_CODE').'</div>'."\n";
$html .= "<pre id=\"htmlsharecode\">\n";
$html .= htmlentities('<iframe class="auto-resize" width="100%" scroll="no" frameborder="0" src="'.$this->Href('iframe').'"></iframe>')."\n";
$html .= "</pre>\n";
$html .= '
<div class="checkbox">
  <label>
    <input id="checkbox-share" type="checkbox" onclick="document.getElementById(\'htmlsharecode\').textContent = this.checked ? document.getElementById(\'htmlsharecode\').textContent.replace(\'&share=1\', \'\') : document.getElementById(\'htmlsharecode\').textContent.replace(\''.$this->Href('iframe').'\', \''.$this->Href('iframe').'&share=1\');"> '._t('TEMPLATE_ADD_SHARE_BUTTON').'
  </label>
</div>
<div class="checkbox">
  <label>
    <input id="checkbox-edit" type="checkbox" onclick="document.getElementById(\'htmlsharecode\').textContent = this.checked ? document.getElementById(\'htmlsharecode\').textContent.replace(\'\&edit\=1\', \'\') : document.getElementById(\'htmlsharecode\').textContent.replace(\''.$this->Href('iframe').'\', \''.$this->Href('iframe').'&edit=1\');"> '._t('TEMPLATE_ADD_EDIT_BAR').'
  </label>
</div>
';

// si l'on est dans une requete ajax, pas besoin de titre, et pas besoin de charger tout le html
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo utf8_encode('<div class="page">'."\n".$html."\n".'<div>');
} else {
    echo $this->Header();
    echo "<div class=\"page\">\n<h2>"._t('TEMPLATE_SEE_SHARING_OPTIONS').' '.$this->GetPageTag()."</h2>\n$html\n<hr class=\"hr_clear\" />\n</div>\n";
    echo $this->Footer();
}
