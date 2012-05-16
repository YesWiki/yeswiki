<?php
/*
$Id: sync.php,v 1.1 2010-07-22 14:21:10 mrflos Exp $
Copyright (c) 2010, Florian Schmitt <florian@outils-reseaux.org>
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

echo $this->Header();
echo '<h1>Synchronisation</h1>'."\n";
echo '<h2>Import à partir d\'un autre YesWiki</h2>'."\n";

if (isset($_POST['urltosync']) && $_POST['urltosync']!='http://') {
	$valurltosync = $_POST['urltosync'];
	
	//teste la validité de l'url
	$regex = "((https?|ftp)\:\/\/)?"; // Scheme
    $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?"; // User and Pass
    $regex .= "([a-z0-9-.]*)\.([a-z]{2,3})"; // Host or IP
    $regex .= "(\:[0-9]{2,5})?"; // Port
    $regex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?"; // Path
    $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?"; // GET Query
    $regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?"; // Anchor
 
    if (preg_match("/^$regex$/", $valurltosync)) {
		$url = explode('wakka.php', $_POST['urltosync']);
		$wakkadress = $url[0].'/'.'wakka.php?wiki=ImporT/jsonp&demand=pages';
		//echo '<h3>Les pages de '.$wakkadress.'</h3>';
		$json = json_decode(file_get_contents($wakkadress), true);
		$script = '<script type="text/javascript" language="javascript" src="tools/bazar/libs/jquery.dataTables.min.js"></script>
		<script type="text/javascript" charset="utf-8">
			$(document).ready(function() {
				$(\'#table_import\').dataTable( {
					"bJQueryUI": true,
					"sPaginationType": "full_numbers",
					"bProcessing": true,
					"bServerSide": true,
					"sAjaxSource": "'.$wakkadress.'",
					"fnServerData": function( sUrl, aoData, fnCallback ) {
						$.ajax( {
							"url": sUrl,
							"data": aoData,
							"success": fnCallback,
							"dataType": "jsonp",
							"cache": false
						} );
					},
					"oLanguage": {
						"sProcessing": "Traitement...",
						"sLengthMenu": "Afficher _MENU_ enregistrements par page",
						"sEmptyTable": "Pas de donn&eacute;es disponibles dans la table",
						"sLoadingRecords":"Chargement...",
						"sZeroRecords": "Pas des r&eacute;sultats trouv&eacute;s...",
						"sInfo": "Montrer de _START_ &agrave; _END_ sur _TOTAL_ lignes",
						"sInfoEmpty": "Montrer de 0 &agrave; 0 de 0 lignes",
						"sInfoFiltered": "(_MAX_ lignes au total)",
						"sSearch": "Filtrer:",
						"sFirst": "Premier",
						"sPrevious": "Pr&eacute;c&eacute;dent",
						"sNext": "Suivant",
						"sLast": "Dernier"
					}
				} );
			} );
		</script>';
		$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'].$script : $script);
		echo '<table cellpadding="0" cellspacing="0" border="0" class="display" id="table_import">
	<thead>
		<tr>
			<th width="33%">NomWiki</th>
			<th width="33%">Dernière mise à jour</th>
			<th width="33%">Propriétaire</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td colspan="3" class="dataTables_empty">Loading data from server</td>
		</tr>
	</tbody>
	<tfoot>
		<tr>
			<th width="33%">NomWiki</th>
			<th width="33%">Dernière mise à jour</th>
			<th width="33%">Propriétaire</th>
		</tr>
	</tfoot>
</table>';
		//var_dump($json);
	} else {
		echo '<div class="error_box">L\'URL entr&eacute;e n\'est pas valide, elle doit commencer par http:// et ne pas contenir d\'espaces.</div>';
	}


} else {
	 $valurltosync = 'http://';
}

echo $this->FormOpen('sync'); 
echo '<input name="urltosync" type="text" value="'.$valurltosync.'" />'."\n";
echo '<input name="submit_export" type="submit" value="Afficher les pages &agrave; importer" />'."\n";
echo $this->FormClose();

echo $this->Footer();
?>
