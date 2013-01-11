<?php 
//Retourne le timestamp du d�but du mois du timestamp renseign�
function getMonthStartTS($in_timeStamp) { 
	return mktime( 0, 1, 1, date("m", $in_timeStamp), 1, 
		date("Y", $in_timeStamp)); 					
}

//Retourne le timestamp de la fin du mois du timestamp renseign�
function getMonthEndTS($in_timeStamp) { 
	return mktime( 23,	59,	59, date("m", $in_timeStamp)+1, 1,
		date("Y", $in_timeStamp)); 
}

//Retourne le timestamp du d�but de la semaine du timestamp renseign�
function getWeekStartTS($in_timeStamp) { 
	
}

//Retourne le timestamp de la fin de la semaine mois du timestamp renseign�
function getWeekEndTS($in_timeStamp) { 
	
}

//Retourne le timestamp du d�but du jour du timestamp renseign�
function getDayStartTS($in_timeStamp) { 

}

//Retourne le timestamp de la fin du jour du timestamp renseign�
function getDayEndTS($in_timeStamp) { 

}

 /***************************************************************************
 * Elimine les evenement en dehors de l'intervalle pr�cis�
 * et range ceux restant par ordre chronologique.
 */
function filterEvents($in_startTS, $in_endTS, $in_data) {
	$selectedData = array();
	//Filtre les �venements
	foreach($in_data as $event) {
		//TODO : prendre en compte les �venement sur plusieurs jours/mois/ann�es/mill�naires
		//       decouper les �l�ments au jour le jour.
		if (($event["DTSTART"]["unixtime"] <= $in_endTS) 
			&& ($event["DTEND"]["unixtime"] >= $in_startTS)) {
				array_push($selectedData, $event);		
		}
	}/**/
	return $selectedData;	
}

/****************************************************************************
 * Cr�e le squelette de donn�e du calendrier (Vue mensuelle).
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
	//Les jours vide de d�but de mois
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

	print("<div class='calendar' style='background-color: ".$in_color.";'>\n");
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
	print("<ul class=\"select_date\">"
			."<li class=\"list month_list\">".$lsMonth[$moisencours]);
	//Liste des mois		
	print("<ul class=\"select\" style='background-color: ".$in_color.";'>\n");
	for ($i=1; $i<=12; $i++){
		print("\t<li><a class=\"list select_item\" href=\"tools/wikical/actions/cal.php?timestamp=".mktime(0, 0, 0, $i, $jourencours, $anneeencours).$url_params."\">".$lsMonth[$i]."</a></li>\n");
	}
	print("</ul></li>\n");
	//Liste des ann�es
	print("<li class=\"list year_list\">".$anneeencours);
	print("<ul class=\"select\" style='background-color: ".$in_color.";'>\n");
	for($i=-4;$i<3;$i++) {
		print("\t<li><a class=\"select_item\" href=\"tools/wikical/actions/cal.php?timestamp=".mktime(0, 0, 0, $moisencours, $jourencours, $anneeencours+$i).$url_params.'">'.($anneeencours+$i)."</a></li>\n");	
	}
	print("</ul></li>\n");
	
	print("\n
		<li class=\"list\"><a href=\"tools/wikical/actions/cal.php?timestamp=".$prev_month.$url_params."\" class=\"cal_prev prev_month\" title=\"Mois pr&eacute;c&eacute;dent\">&lt;</a></li>
		<li class=\"list\"><a href=\"tools/wikical/actions/cal.php?timestamp=".time().$url_params."\" class=\"cal_now today\" title=\"Aujourd'hui\">o</a></li>
		<li class=\"list\"><a href=\"tools/wikical/actions/cal.php?timestamp=".$next_month.$url_params."\" class=\"cal_next next_month\" title=\"Mois suivant\">></a></li>\n");
	
	print("</ul>\n");
	print("</div>");
	
	print("<table class='cal_content'>\n");
	print("\t<tr>");
	print("\t\t<th>Lun</th><th>Mar</th><th>Mer</th><th>Jeu</th><th>Ven</th><th>Sam</th><th>Dim</th>");
	print("\t</tr>\n");
	print("\t<tr>");
	
	$compteur = 0;
	
	foreach($in_data as $day) {
		
		
		if ( ($compteur % 7 == 0) && ($compteur != 0) ) {
			print("\t</tr>\n\t<tr>\n");
		}
		
		//Creation de la cellule
		if ($day["isToday"])
			print("\t\t<td class='day today'>");
		else if ($day["isEvent"])
			print("\t\t<td class='day evday'>");
		else
			print("\t\t<td class='day'>");
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
				//Evenement sur une journ�e.
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
		}/**/
		print ("</td>\n");
		
		$compteur += 1;
	}
	
	print("\t</tr>\n");
	print("</table>");
	

	print("</div>\n");
	print("</div>\n");
	print("<script type=\"text/javascript\"><!--
		$(function() {
			//liens pour se d�placer dans le calendrier
			$(\".next_month, .prev_month, .today, .select_item\").live('click', function() {
				var htmlcal = $(this).attr('href') + ' .calendar_content';
				var calheight = $(this).parents('.calendar').height();
				$(this).parents('.calendar').html('<div style=\"height:'+calheight+'px;background:transparent url(tools/wikical/presentation/images/loading.gif) no-repeat center center;\"></div>').load(htmlcal);
				return false;
			});
			
			//listes d�roulantes de s�lection de date
			$(\".select_annee, .select_mois\").live('change', function() {
				var htmlcal = $(this).find(\"option:selected\").val() + ' .calendar_content';
				var calheight = $(this).parents('.calendar').height();
				$(this).parents('.calendar').html('<div style=\"height:'+calheight+'px;background:transparent url(tools/wikical/presentation/images/loading.gif) no-repeat center center;\"></div>').load(htmlcal);
				return false;
			});
		});
		--></script>");
}
?>
