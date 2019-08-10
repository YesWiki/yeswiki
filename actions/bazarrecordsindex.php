<?php
/*
bazarrecordsindex.php
lists only bazar records.

@licence: AGPL
*/
if ($pages = $this->LoadAll('SELECT body FROM ' . $this->config["table_prefix"] . 'pages WHERE latest = \'Y\' AND comment_on=\'\' AND body LIKE \'{"%\' AND tag IN (SELECT DISTINCT resource FROM yeswiki_triples WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type")'))
{
	$pagesarray = [];
	foreach ($pages as $page)
	{
        $fiche = json_decode($page['body'], true);
        if ($fiche) {
            $pagesarray[$fiche['id_fiche']] = $fiche['bf_titre'];
        }
	}
	asort($pagesarray);
	foreach ($pagesarray as $tag => $page)
	{
		// XXX: strtoupper is locale dependent
		$firstChar = strtoupper($page[0]);
		if (!preg_match('/'.WN_UPPER.'/', $firstChar)) {
			$firstChar = '#';
		}

		if (empty($curChar) || $firstChar != $curChar) {
			if (!empty($curChar)) echo "<br />\n" ;
			echo "<b>$firstChar</b><br />\n" ;
			$curChar = $firstChar;
		}
		echo $this->Format('[['.$tag.' '.$page.']]')."<br />";
	}
}
else
{
	echo '<i>'._t('NO_PAGE_FOUND').'.</i>' ;
}

?>
