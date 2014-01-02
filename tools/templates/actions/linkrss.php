<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

echo "\n".
'	<!-- RSS links -->'."\n".
'	<link rel="alternate" type="application/rss+xml" title="'._t('TEMPLATE_RSS_LAST_CHANGES').'" href="'.$this->href('xml', 'DerniersChangementsRSS').'" />'."\n".
'	<link rel="alternate" type="application/rss+xml" title="'._t('TEMPLATE_RSS_LAST_COMMENTS').'" href="'.$this->href('xml', 'DerniersCommentairesRSS').'" />'."\n";
?>
