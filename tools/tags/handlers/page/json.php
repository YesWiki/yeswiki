<?php
// Vérification de sécurité
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
			$response[] = array($tab_les_tags['value'], $tab_les_tags['value'], null, $tab_les_tags['value']);
		}
}
sort($response);
//$names = array('Abraham Lincoln', 'Adolf Hitler', 'Agent Smith', 'Agnus', 'AIAI', 'Akira Shoji', 'Akuma', 'Alex', 'Antoinetta Marie', 'Baal', 'Baby Luigi', 'Backpack', 'Baralai', 'Bardock', 'Baron Mordo', 'Barthello', 'Blanka', 'Bloody Brad', 'Cagnazo', 'Calonord', 'Calypso', 'Cao Cao', 'Captain America', 'Chang', 'Cheato', 'Cheshire Cat', 'Daegon', 'Dampe', 'Daniel Carrington', 'Daniel Lang', 'Dan Severn', 'Darkman', 'Darth Vader', 'Dingodile', 'Dmitri Petrovic', 'Ebonroc', 'Ecco the Dolphin', 'Echidna', 'Edea Kramer', 'Edward van Helgen', 'Elena', 'Eulogy Jones', 'Excella Gionne', 'Ezekial Freeman', 'Fakeman', 'Fasha', 'Fawful', 'Fergie', 'Firebrand', 'Fresh Prince', 'Frylock', 'Fyrus', 'Lamarr', 'Lazarus', 'Lebron James', 'Lee Hong', 'Lemmy Koopa', 'Leon Belmont', 'Lewton', 'Lex Luthor', 'Lighter', 'Lulu');

// make sure they're sorted alphabetically, for binary search tests
//sort($names);

//foreach ($tab_tous_les_tags as $i => $name)
//{
//	$response[] = array($i, $name, null, $name);
//}

header('Content-type: application/json');
echo json_encode($response);
?>
