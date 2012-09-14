<?php
/**
* calendrier : programme affichant les evenements du bazar sous forme de Calendrier dans wikini
*
*
*@package Bazar
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@version       $Revision: 1.2 $ $Date: 2011-07-13 10:33:23 $
// +------------------------------------------------------------------------------------------------------+
*/

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}


//récupération des paramètres wikini
$categorie_nature = $this->GetParameter("categorienature");
if (!empty($categorie_nature)) {
	$GLOBALS['_BAZAR_']['categorie_nature'] = $categorie_nature;
}
//si rien n'est donne, on affiche toutes les categories
else {
	$GLOBALS['_BAZAR_']['categorie_nature'] = 'toutes';
}
$id_typeannonce = $this->GetParameter("idtypeannonce");
if (!empty($id_typeannonce)) {
	$GLOBALS['_BAZAR_']['id_typeannonce'] = $id_typeannonce;
}
//si rien n'est donne, on affiche toutes les annonces
else {
	$GLOBALS['_BAZAR_']['id_typeannonce'] = 'toutes';
}

//on récupère les paramètres pour une requête spécifique
$query = $this->GetParameter("query");
if (!empty($query)) {
	$tabquery = array();
	$tableau = array();
	$tab = explode('|', $query);
	foreach ($tab as $req)
	{
		$tabdecoup = explode('=', $req, 2);
		$tableau[$tabdecoup[0]] = trim($tabdecoup[1]);
	}
	$tabquery = array_merge($tabquery, $tableau);
}
else
{
	$tabquery = '';
}

$tableau_resultat = baz_requete_recherche_fiches($tabquery, '', $GLOBALS['_BAZAR_']['id_typeannonce'], $GLOBALS['_BAZAR_']['categorie_nature']);
$js = '';
foreach ($tableau_resultat as $fiche)
{
	$valeurs_fiche = json_decode($fiche[0], true);
	$valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
	if (isset($valeurs_fiche['bf_date_debut_evenement']) && isset($valeurs_fiche['bf_date_fin_evenement'])) {
		$js .= '{
					title: "'.addslashes($valeurs_fiche['bf_titre']).'",
					start: new Date('.date('Y', strtotime($valeurs_fiche['bf_date_debut_evenement'])).','.(date('n', strtotime($valeurs_fiche['bf_date_debut_evenement']))-1).','.date('d', strtotime($valeurs_fiche['bf_date_debut_evenement'])).'),
					end: new Date('.date('Y', strtotime($valeurs_fiche['bf_date_fin_evenement'])).','.(date('n', strtotime($valeurs_fiche['bf_date_fin_evenement']))-1).','.date('d', strtotime($valeurs_fiche['bf_date_fin_evenement'])).'),
					url:"'.$GLOBALS['wiki']->config['base_url'].$valeurs_fiche['id_fiche'].'"
		},';
	}
}
$js = substr($js,0,-1);
	
$GLOBALS['js'] .=  "<script type='text/javascript' src='tools/bazar/libs/fullcalendar/fullcalendar.min.js'></script>
<script type='text/javascript'>

	$(document).ready(function() {
	
		var date = new Date();
		var d = date.getDate();
		var m = date.getMonth();
		var y = date.getFullYear();
		
		$('#calendar').fullCalendar({
			header: {
				left: 'prev,next today',
				center: 'title',
				right: 'month,agendaWeek,agendaDay'
			},
			editable: false,
			events: [
				".$js."
			],
			monthNames: ['Janvier','F&eacute;vrier','Mars','Avril','Mai','Juin','Juillet','Ao&ucirc;t','Septembre','Octobre','Novembre','D&eacute;cembre'],
			monthNamesShort: ['Jan','Fev','Mar','Avr','Mai','Juin','Juil','Aug','Sep','Oct','Nov','Dec'],
			dayNames: ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'],
			dayNamesShort: ['Dim','Lun','Mar','Mer','Jeu','Vendredi','Sam'],
			buttonText: {
				prev: '&nbsp;&#9668;&nbsp;',
				next: '&nbsp;&#9658;&nbsp;',
				prevYear: '&nbsp;&lt;&lt;&nbsp;',
				nextYear: '&nbsp;&gt;&gt;&nbsp;',
				today: 'Aujourd\'hui',
				month: 'Mois',
				week: 'Semaine',
				day: 'Jour'
			}
		});
		
	});

</script>";
echo "<div id='calendar'></div>";

?>
