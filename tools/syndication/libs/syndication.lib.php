<?php
/**
 * Fonction getRelativeDate
 * par Jay Salvat - http://blog.jaysalvat.com/*/
function getRelativeDate($date) {
	error_reporting(E_ALL);
    // Les paramètres locaux sont basés sur la France
    setlocale(LC_TIME, 'fr', 'fr_FR', 'french', 'fra', 'fra_FRA', 'fr_FR.ISO_8859-1', 'fra_FRA.ISO_8859-1', 'fr_FR.utf8', 'fr_FR.utf-8', 'fra_FRA.utf8', 'fra_FRA.utf-8');

    // On prend divers points de repère dans le temps
    $time            = strtotime($date);
    $after           = strtotime("+7 day 00:00");
    $afterTomorrow   = strtotime("+2 day 00:00");
    $tomorrow        = strtotime("+1 day 00:00");
    $today           = strtotime("today 00:00");
    $yesterday       = strtotime("-1 day 00:00");
    $beforeYesterday = strtotime("-2 day 00:00");
    $before          = strtotime("-7 day 00:00");
    // On compare les repères à la date actuelle
    // si elle est proche alors on retourne une date relative...
    if ($time < $after && $time > $before) {
        if ($time >= $after) {
            $relative = strftime("%A", $date)." prochain";
        } else if ($time >= $afterTomorrow) {
            $relative = "Apr&egrave;s demain";
        } else if ($time >= $tomorrow) {
            $relative = "Demain";
        } else if ($time >= $today) {
            $relative = "Aujourd'hui";
        } else if ($time >= $yesterday) {
            $relative = "Hier";
        } else if ($time >= $beforeYesterday) {
            $relative = "Avant hier";
        } else if ($time >= $before) {
            $relative = strftime("%A", $time)." dernier";
        }
    // sinon on retourne une date complète.
    } else {
        $relative = 'Le '.strftime("%A %d %B %Y", $time);
    }
    // si l'heure est présente dans la date originale, on l'ajoute
    if (preg_match('/[0-9]{2}:[0-9]{2}/', $date)) {
        $relative .= ' &agrave; '.date('H:i', $time);
    }
    return $relative;
}
?>