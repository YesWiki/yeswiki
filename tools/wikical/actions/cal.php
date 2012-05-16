<?php
 /***************************************************************************
 /* DOCUMENTATION
 ****************************************************************************

  Usage : {{cal src="http://mon.domai.ne/path/fichier.ics"}}

  **************************************************************************/

//on appele le fichier sans passer par wikini (pour l'appel ajax)
if (!defined("WIKINI_VERSION"))
{
	$params['url'] = urldecode($_GET["url"]);
	$params['color'] = urldecode($_GET["color"]);
	$params['class'] = urldecode($_GET["class"]);
	$params['link'] = urldecode($_GET["link"]);
	include_once '../libs/ical.php';
	include_once '../libs/functions.ical.php';
}

//on est dans wikini
else {
	$params['url'] = $this->GetParameter("url");
	$params['color'] = $this->GetParameter("color");
	$params['class'] = $this->GetParameter("class");
	$params['link'] = $this->GetParameter("link");
	include_once 'tools/wikical/libs/ical.php';
	include_once 'tools/wikical/libs/functions.ical.php';
}

$cal = new ical();
$cal->parse($params['url']);
$data = $cal->get_event_list();

if (isset($_GET['timestamp'])) {
	$daytime = $_GET['timestamp'];
} 
//si pas de date
else {
	$daytime = time();
}

$data = filterEvents(getMonthStartTS($daytime), getMonthEndTS($daytime), $data);

$data = makeMonth($daytime, $data);

printMonthCal($daytime, $data, $params);

?>