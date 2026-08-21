<?php

// function pour troquer une chaine sans casser les balises
/**
 * Truncates text.
 *
 * Cuts a string to the length of $length and replaces the last characters
 * with the ending if the text is longer than length.
 *
 * @param string $text   string to truncate
 * @param int    $length length of returned string, including ellipsis
 *
 * @return string trimmed string
 */
function truncate($text, $length = 100, $append = '&hellip;')
{
    $string = trim($text);

    if (strlen($string) > $length) {
        $string = wordwrap($string, $length);
        $string = explode("\n", $string, 2);
        $string = $string[0] . $append;
    }

    return $string;
}

/**
 * Fonction getRelativeDate.
 * par Jay Salvat - http://blog.jaysalvat.com/*/
function getRelativeDate($date)
{
    // Les paramètres locaux sont basés sur la France
    setlocale(LC_TIME, 'fr', 'fr_FR', 'french', 'fra', 'fra_FRA', 'fr_FR.ISO_8859-1', 'fra_FRA.ISO_8859-1', 'fr_FR.utf8', 'fr_FR.utf-8', 'fra_FRA.utf8', 'fra_FRA.utf-8');

    // On prend divers points de repère dans le temps
    $time = strtotime($date);
    $after = strtotime('+7 day 00:00');
    $afterTomorrow = strtotime('+2 day 00:00');
    $tomorrow = strtotime('+1 day 00:00');
    $today = strtotime('today 00:00');
    $yesterday = strtotime('-1 day 00:00');
    $beforeYesterday = strtotime('-2 day 00:00');
    $before = strtotime('-7 day 00:00');
    // On compare les repères à la date actuelle
    // si elle est proche alors on retourne une date relative...
    if ($time < $after && $time > $before) {
        if ($time >= $after) {
            $relative = date('l', $date) . ' prochain';
        } elseif ($time >= $afterTomorrow) {
            $relative = 'Après demain';
        } elseif ($time >= $tomorrow) {
            $relative = 'Demain';
        } elseif ($time >= $today) {
            $relative = "Aujourd'hui";
        } elseif ($time >= $yesterday) {
            $relative = 'Hier';
        } elseif ($time >= $beforeYesterday) {
            $relative = 'Avant hier';
        } elseif ($time >= $before) {
            $relative = date('l', $time) . ' dernier';
        }
    // sinon on retourne une date complète.
    } else {
        $relative = 'Le ' . date('j.n.Y', $time);
    }
    // si l'heure est présente dans la date originale, on l'ajoute
    if (preg_match('/[0-9]{2}:[0-9]{2}/', $date)) {
        $relative .= ' à ' . date('H:i', $time);
    }

    return $relative;
}
