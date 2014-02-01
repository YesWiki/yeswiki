<?php
// Verification de securite
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}
$response = array();
$tab_tous_les_tags = $this->GetAllTags();
if (is_array($tab_tous_les_tags))
{
		foreach ($tab_tous_les_tags as $tab_les_tags)
		{
			$tab_les_tags['value'] = _convert($tab_les_tags['value'], 'ISO-8859-1')
			$response[] = array($tab_les_tags['value'], $tab_les_tags['value'], null, $tab_les_tags['value']);
		}
}
sort($response);

header('Content-type: application/json');
echo json_encode($response);
?>
