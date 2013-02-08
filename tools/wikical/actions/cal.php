<?php
 /***************************************************************************
 /* DOCUMENTATION
 ****************************************************************************

  Usage : {{cal src="http://mon.domai.ne/path/fichier.ics"}}

  **************************************************************************/

//Ajout du CSS et des javascripts
$GLOBALS['js'] = (isset($GLOBALS['js']) 
	? $GLOBALS['js'] 
	: '').'  <link rel="stylesheet" href="tools/wikical/presentation/styles/cal.css" /><script src="tools/wikical/libs/wikical.js">'."\n";


//on appele le fichier sans passer par wikini (pour l'appel ajax)

$params['url'] = $this->GetParameter("url");
$params['color'] = $this->GetParameter("color");
$params['class'] = $this->GetParameter("class");
$params['link'] = $this->GetParameter("link");
include_once 'tools/wikical/libs/ical.php';
include_once 'tools/wikical/libs/functions.ical.php';



/*$cal = new ical();
$cal->parse($params['url']);
$data = $cal->get_event_list();*/

if (isset($_GET['timestamp'])) {
	$daytime = $_GET['timestamp'];
} 
//si pas de date
else {
	$daytime = time();
}

/*$data = filterEvents(getMonthStartTS($daytime), getMonthEndTS($daytime), $data);

$data = makeMonth($daytime, $data);

printMonthCal($daytime, $data, $params);*/

firstLoad($daytime, $params);


?>