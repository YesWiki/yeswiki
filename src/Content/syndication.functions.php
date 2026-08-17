<?php

/**
 * Truncates text.
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
