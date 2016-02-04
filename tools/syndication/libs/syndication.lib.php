<?php

//function pour troquer une chaine sans casser les balises
/**
 * Truncates text.
 *
 * Cuts a string to the length of $length and replaces the last characters
 * with the ending if the text is longer than length.
 *
 * @param string $text         String to truncate.
 * @param int    $length       Length of returned string, including ellipsis.
 * @param string $ending       Ending to be appended to the trimmed string.
 * @param bool   $exact        If false, $text will not be cut mid-word
 * @param bool   $considerHtml If true, HTML tags would be handled correctly
 *
 * @return string Trimmed string.
 */
function truncate($text, $length = 100, $ending = ' [..]', $exact = false, $considerHtml = true)
{
    if ($considerHtml) {

        // if the plain text is shorter than the maximum length, return the whole text
        if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
            return $text;
        }

        // splits all html-tags to scanable lines
        preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
        $total_length = strlen($ending);
        $open_tags = array();
        $truncate = '';
        foreach ($lines as $line_matchings) {

            // if there is any html-tag in this line, handle it and add it (uncounted) to the output
            if (!empty($line_matchings[1])) {

                // if it's an "empty element" with or without xhtml-conform closing slash (f.e. <br/>)
                if (preg_match(
                    '/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)'
                    .'(\s.+?)?)>$/is',
                    $line_matchings[1]
                )) {

                    // do nothing
                    // if tag is a closing tag (f.e. </b>)
                } elseif (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {

                    // delete tag from $open_tags list
                    $pos = array_search($tag_matchings[1], $open_tags);
                    if ($pos !== false) {
                        unset($open_tags[$pos]);
                    }

                    // if tag is an opening tag (f.e. <b>)
                } elseif (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {

                    // add tag to the beginning of $open_tags list
                    array_unshift($open_tags, strtolower($tag_matchings[1]));
                }

                // add html-tag to $truncate'd text
                $truncate .= $line_matchings[1];
            }

            // calculate the length of the plain text part of the line; handle entities as one character
            $content_length = strlen(
                preg_replace(
                    '/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i',
                    ' ',
                    $line_matchings[2]
                )
            );
            if ($total_length + $content_length > $length) {

                // the number of characters which are left
                $left = $length - $total_length;
                $entities_length = 0;

                // search for html entities
                if (preg_match_all(
                    '/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i',
                    $line_matchings[2],
                    $entities,
                    PREG_OFFSET_CAPTURE
                )) {

                    // calculate the real length of all entities in the legal range
                    foreach ($entities[0] as $entity) {
                        if ($entity[1] + 1 - $entities_length <= $left) {
                            --$left;
                            $entities_length += strlen($entity[0]);
                        } else {
                            // no more characters left
                            break;
                        }
                    }
                }
                $truncate .= substr($line_matchings[2], 0, $left + $entities_length);

                // maximum lenght is reached, so get off the loop
                break;
            } else {
                $truncate .= $line_matchings[2];
                $total_length += $content_length;
            }

            // if the maximum length is reached, get off the loop
            if ($total_length >= $length) {
                break;
            }
        }
    } else {
        if (strlen($text) <= $length) {
            return $text;
        } else {
            $truncate = substr($text, 0, $length - strlen($ending));
        }
    }

    // if the words shouldn't be cut in the middle...
    if (!$exact) {

        // ...search the last occurance of a space...
        $spacepos = strrpos($truncate, ' ');
        if (isset($spacepos)) {

            // ...and cut the text in this position
            $truncate = substr($truncate, 0, $spacepos);
        }
    }

    // add the defined ending to the text
    $truncate .= $ending;
    if ($considerHtml) {

        // close all unclosed html-tags
        foreach ($open_tags as $tag) {
            $truncate .= '</'.$tag.'>';
        }
    }

    return $truncate;
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
            $relative = strftime('%A', $date).' prochain';
        } elseif ($time >= $afterTomorrow) {
            $relative = 'Apr&egrave;s demain';
        } elseif ($time >= $tomorrow) {
            $relative = 'Demain';
        } elseif ($time >= $today) {
            $relative = "Aujourd'hui";
        } elseif ($time >= $yesterday) {
            $relative = 'Hier';
        } elseif ($time >= $beforeYesterday) {
            $relative = 'Avant hier';
        } elseif ($time >= $before) {
            $relative = strftime('%A', $time).' dernier';
        }
    // sinon on retourne une date complète.
    } else {
        $relative = 'Le '.strftime('%A %d %B %Y', $time);
    }
    // si l'heure est présente dans la date originale, on l'ajoute
    if (preg_match('/[0-9]{2}:[0-9]{2}/', $date)) {
        $relative .= ' &agrave; '.date('H:i', $time);
    }

    return $relative;
}
