<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$displayLastChanges = $this->HasAccess('read', 'DerniersChangementsRSS') ;
$displayLastComments = $this->HasAccess('read', 'DerniersCommentairesRSS') ;

if ($displayLastChanges || $displayLastComments) {
	echo "\n".
	'  <!-- RSS links -->'."\n" ;
}
if ($displayLastChanges) {
	echo '  <link rel="alternate" type="application/rss+xml" title="'._t('TEMPLATE_RSS_LAST_CHANGES').'" href="'.$this->href('xml', 'DerniersChangementsRSS').'" />'."\n";
}
if ($displayLastComments) {
	echo '  <link rel="alternate" type="application/rss+xml" title="'._t('TEMPLATE_RSS_LAST_COMMENTS').'" href="'.$this->href('xml', 'DerniersCommentairesRSS').'" />'."\n";
}
?>
