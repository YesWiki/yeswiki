<?php 
//Retourne le timestamp du début du mois du timestamp renseigné
function getMonthStartTS($in_timeStamp) { 
	return mktime( 0, 1, 1, date("m", $in_timeStamp), 1, 
		date("Y", $in_timeStamp)); 					
}

//Retourne le timestamp de la fin du mois du timestamp renseigné
function getMonthEndTS($in_timeStamp) { 
	return mktime( 23,	59,	59, date("m", $in_timeStamp)+1, 1,
		date("Y", $in_timeStamp)); 
}

//Retourne le timestamp du début de la semaine du timestamp renseigné
function getWeekStartTS($in_timeStamp) { 
	
}

//Retourne le timestamp de la fin de la semaine mois du timestamp renseigné
function getWeekEndTS($in_timeStamp) { 
	
}

//Retourne le timestamp du début du jour du timestamp renseigné
function getDayStartTS($in_timeStamp) { 

}

//Retourne le timestamp de la fin du jour du timestamp renseigné
function getDayEndTS($in_timeStamp) { 

}

 /***************************************************************************
 * Elimine les evenement en dehors de l'intervalle précisé
 * et range ceux restant par ordre chronologique.
 */
function filterEvents($in_startTS, $in_endTS, $in_data) {
	$selectedData = array();
	//Filtre les évenements
	foreach($in_data as $event) {
		//TODO : prendre en compte les évenement sur plusieurs jours/mois/années/millénaires
		//       decouper les éléments au jour le jour.
		if (($event["DTSTART"]["unixtime"] <= $in_endTS) 
			&& ($event["DTEND"]["unixtime"] >= $in_startTS)) {
				array_push($selectedData, $event);		
		}
	}/**/
	return $selectedData;	
}

/****************************************************************************
 * Crée le squelette de donnée du calendrier (Vue mensuelle).
 */
function makeMonth($in_timestamp, $in_data)
{
	$startMonthTS = mktime( 0, 0, 0, date("m", $in_timestamp), 1, date("Y", $in_timestamp));
	$firstDay = date("w",$startMonthTS); //0 --> Dimanche...6--> Samedi
	if ($firstDay == 0) 
		$firstDay = 7;
	$firstDay--; //<-- premier jour de la semaine = Lundi

	$nb_jours = date("t", mktime( 0, 1, 1, date("m", $in_timestamp)+1, 0, date("Y", $in_timestamp)));	
	
	$month = array();
	//Les jours vide de début de mois
	for($i=0 ; $i<$firstDay ; $i++){
		$day = array("isBlank" => true, "isToday" => false, "isEvent" => false, "startDayTS" => mktime(0,0,0,0,0,0), "endDayTS" => mktime(0,0,0,0,0,0), "events" => array() );
		array_push($month, $day);
	}
	//ajouter les jours
	for($i=0 ; $i<$nb_jours ; $i++){
		$isToday = false;
		$isEvent = false;
		$startDayTS = mktime(0, 0, 0, date("m", $startMonthTS)  , date("d", $startMonthTS)+$i, date("Y", $startMonthTS));
		$endDayTS = mktime(23, 59, 59, date("m", $startMonthTS)  , date("d", $startMonthTS)+$i, date("Y", $startMonthTS));
		
		if ((time() >= $startDayTS ) && (time() <= $endDayTS ))
			$isToday = true;
		
		$events = array();
		foreach ($in_data as $event){
			if (($event["DTSTART"]["unixtime"] <= $endDayTS) && ($event["DTEND"]["unixtime"] >= $startDayTS)) {
				$event["SUMMARY"] = htmlentities(utf8_decode($event["SUMMARY"]));
				if ($event["DTEND"]["unixtime"] != $startDayTS){
					array_push($events, $event);
					$isEvent = true;
				}
			}
				
		}
		array_push($month, array("isBlank" => false, "isToday" => $isToday, "isEvent" => $isEvent, "startDayTS" => $startDayTS, "endDayTS" => $endDayTS, "events" => $events));
		
	}
	//Les jours de fin de mois.
	if (($nb_jours+$firstDay) % 7 != 0) {
		for($i=0 ; $i < 7 - (($nb_jours+$firstDay) % 7) ; $i++){
			$day = array("isBlank" => true, "isToday" => false, "isEvent" => false, "startDayTS" => mktime(0,0,0,0,0,0), "endDayTS" => mktime(0,0,0,0,0,0), "events" => array() );
			array_push($month, $day);
		}
	}
	
	return $month;
}



/****************************************************************************
 * Affichage du calendrier (vue mois)
 */

function printMonthCal($in_timeStamp, $in_data, $params) {

	if (isset($params['color'])) $in_color = $params['color']; else $in_color = 'grey';
	if (isset($params['url'])) $url = $params['url']; else die('ERREUR action cal : param&ecirc;tre "url" obligatoire!');

	print("<div class='calendar' style='background-color: ".$in_color.";border:1px solid ".$in_color.";'>\n");
	print("<div class='calendar_content'>\n");

	$jourencours = date("j", $in_timeStamp);
	$moisencours = date("n", $in_timeStamp);
	$anneeencours = date("Y", $in_timeStamp);
	$next_month = strtotime('+1 month',$in_timeStamp);
	$prev_month = strtotime('-1 month',$in_timeStamp);
	$url_params = "&amp;url=".urlencode($url)."&amp;color=".urlencode($in_color);
	
	$lsMonth = array(1 => "Janvier",
					 2 => "F&eacute;vrier",
					 3 => "Mars",
					 4 => "Avril",
					 5 => "Mai",
					 6 => "Juin",
					 7 => "Juillet",
					 8 => "Ao&ucirc;t",
					 9 => "Septembre",
					 10 => "Octobre",
					 11 => "Novembre",
					 12 => "D&eacute;cembre");
	
	print("<div class=\"cal_entete\">");
	print("<ul class=\"select_date\">");
	print("<li class=\"list\"><a href=\"tools/wikical/libs/update.php?timestamp=".$prev_month.$url_params."\" class=\"cal_prev prev_month\" title=\"Mois pr&eacute;c&eacute;dent\">&lt;&lt;</a></li>");
	print("<li class=\"list month_list\">".$lsMonth[$moisencours]);
	//Liste des mois		
	print("<ul class=\"select\" style='background-color: ".$in_color.";'>\n");
	for ($i=1; $i<=12; $i++){
		print("\t<li><a class=\"select_item\" href=\"tools/wikical/libs/update.php?timestamp=".mktime(0, 0, 0, $i, $jourencours, $anneeencours).$url_params."\">".$lsMonth[$i]."</a></li>\n");
	}
	print("</ul></li>\n");
	//Liste des années
	print("<li class=\"list year_list\">".$anneeencours);
	print("<ul class=\"select\" style='background-color: ".$in_color.";'>\n");
	for($i=-4;$i<3;$i++) {
		print("\t<li><a class=\"select_item\" href=\"tools/wikical/libs/update.php?timestamp=".mktime(0, 0, 0, $moisencours, $jourencours, $anneeencours+$i).$url_params.'">'.($anneeencours+$i)."</a></li>\n");	
	}
	print("</ul></li>\n");
	
	print("\n
		<li class=\"list\"><a href=\"tools/wikical/libs/update.php?timestamp=".$next_month.$url_params."\" class=\"cal_next next_month\" title=\"Mois suivant\">&gt;&gt;</a></li>\n
		<li class=\"list aujourdhui\"><a href=\"tools/wikical/libs/update.php?timestamp=".time().$url_params."\" class=\"cal_now today\" title=\"Aujourd'hui\">Aujourd'hui</a></li>");
	
	print("</ul>\n");
	print("</div>");
	
print("<table class='cal_contenu'>\n"); 
	print("<tr class='tr_entete'>\n"); 
		print("<td class='day_name'>Lun</td>\n");
		print("<td class='day_name'>Mar</td>\n");
		print("<td class='day_name'>Mer</td>\n");
		print("<td class='day_name'>Jeu</td>\n");
		print("<td class='day_name'>Ven</td>\n");
		print("<td class='day_name'>Sam</td>\n");
		print("<td class='day_name'>Dim</td>\n");
	print("</tr>\n"); 

//création des tr
$ligne=0;
$nb = 7;

	foreach($in_data as $day) {

 //si 1ere élement on commence une ligne 
	$start = ($ligne%$nb == 0)?"<tr>":"";
 //si dernier élément on finit la ligne 
	$end = ($ligne%$nb == $nb-1)?"</tr>\n":""; 
print("$start"); 
 //on affiche  
		//Creation du DIV
		if ($day["isToday"])
			print("<td class='day today'>");
		else if ($day["isEvent"])
			print("<td class='day evday'>");
		else
			print("<td class='day'>");
		//Contenu du DIV
		if(!$day["isBlank"])
			print(date("d",$day['startDayTS']));
		//affichage des events
		if ($day["isEvent"]) {
			print ("<div class='events'>");
			foreach($day["events"] as $event) {
				print("<p class='event_title'>".$event["SUMMARY"]."</p>");
				//TODO : Gerer toutes les infos
				//       Ajouter une boucle. 
				
				//Evenement sur plusieurs jours.
				if ( ($event["DTEND"]["unixtime"] - $event["DTSTART"]["unixtime"]) > 86400) {
					print("<p class='event_info'>Du "
						.date("d/m/Y", $event["DTSTART"]["unixtime"])
						." &agrave; ".date("G:i", $event["DTSTART"]["unixtime"])
						." au ".date("d/m/Y", $event["DTEND"]["unixtime"] )
						." &agrave; ".date("G:i", $event["DTEND"]["unixtime"])."</p>\n");
				}		
				//Evenement sur une journée.
				elseif (($event["DTEND"]["unixtime"] - $event["DTSTART"]["unixtime"]) == 86400) {
					print ("<p class='event_info'>&Eacute;v&egrave;nement sur la journ&eacute;e.</p>");	
				} 
				//
				else 
					print("<p class='event_info'>De "
						.date("H:i", $event["DTSTART"]["unixtime"])
						." &agrave; "
						.date("H:i", $event["DTEND"]["unixtime"])
						."</p>\n");
			}
			print ("</div>\n");
		}
		print ("</td>\n");
		print("$end");
		$ligne = $ligne + 1;
	}
	print("</table>\n");
	print("</div>\n");
	print("</div>\n");
}

/////////////////////////////////////////////////////////////////////////////

function firstLoad($in_timeStamp, $params) {

		if (isset($params['color'])) $in_color = $params['color']; else $in_color = 'grey';
	if (isset($params['url'])) $url = $params['url']; else die('ERREUR action cal : param&ecirc;tre "url" obligatoire!');

	print("<div class='calendar' style='background-color: ".$in_color.";border:1px solid ".$in_color.";'>\n");
	print("<div class='calendar_content'>\n");

	$jourencours = date("j", $in_timeStamp);
	$moisencours = date("n", $in_timeStamp);
	$anneeencours = date("Y", $in_timeStamp);
	$next_month = strtotime('+1 month',$in_timeStamp);
	$prev_month = strtotime('-1 month',$in_timeStamp);
	$url_params = "&amp;url=".urlencode($url)."&amp;color=".urlencode($in_color);
	
	$lsMonth = array(1 => "Janvier",
					 2 => "F&eacute;vrier",
					 3 => "Mars",
					 4 => "Avril",
					 5 => "Mai",
					 6 => "Juin",
					 7 => "Juillet",
					 8 => "Ao&ucirc;t",
					 9 => "Septembre",
					 10 => "Octobre",
					 11 => "Novembre",
					 12 => "D&eacute;cembre");
	
	print("<div class=\"cal_entete\">");
	print("<ul class=\"select_date\">");
	print("<li class=\"list\"><a href=\"tools/wikical/libs/update.php?timestamp=".$prev_month.$url_params."\" class=\"cal_prev prev_month\" title=\"Mois pr&eacute;c&eacute;dent\">&lt;&lt;</a></li>");
	print("<li class=\"list month_list\">".$lsMonth[$moisencours]);
	//Liste des mois		
	print("<ul class=\"select\" style='background-color: ".$in_color.";'>\n");
	for ($i=1; $i<=12; $i++){
		print("\t<li><a class=\"select_item\" href=\"tools/wikical/libs/update.php?timestamp=".mktime(0, 0, 0, $i, $jourencours, $anneeencours).$url_params."\">".$lsMonth[$i]."</a></li>\n");
	}
	print("</ul></li>\n");
	//Liste des années
	print("<li class=\"list year_list\">".$anneeencours);
	print("<ul class=\"select\" style='background-color: ".$in_color.";'>\n");
	for($i=-4;$i<3;$i++) {
		print("\t<li><a class=\"select_item\" href=\"tools/wikical/libs/update.php?timestamp=".mktime(0, 0, 0, $moisencours, $jourencours, $anneeencours+$i).$url_params.'">'.($anneeencours+$i)."</a></li>\n");	
	}
	print("</ul></li>\n");
	
	print("\n
		<li class=\"list\"><a href=\"tools/wikical/libs/update.php?timestamp=".$next_month.$url_params."\" class=\"cal_next next_month\" title=\"Mois suivant\">&gt;&gt;</a></li>\n
		<li class=\"list aujourdhui\"><a href=\"tools/wikical/libs/update.php?timestamp=".time().$url_params."\" class=\"cal_now today\" title=\"Aujourd'hui\">Aujourd'hui</a></li>");
	
	print("</ul>\n");
	print("</div>");
	
print("<table class='cal_contenu'>\n"); 
	print("<tr class='tr_entete'>\n"); 
		print("<td class='day_name'>Lun</td>\n");
		print("<td class='day_name'>Mar</td>\n");
		print("<td class='day_name'>Mer</td>\n");
		print("<td class='day_name'>Jeu</td>\n");
		print("<td class='day_name'>Ven</td>\n");
		print("<td class='day_name'>Sam</td>\n");
		print("<td class='day_name'>Dim</td>\n");
	print("</tr>\n"); 


	print("</table>\n");
	print("</div>\n");
	print("</div>\n");
}

?>
