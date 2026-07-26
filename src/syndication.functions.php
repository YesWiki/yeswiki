<?php

// relocated from tools/syndication/libs/syndication.lib.php (ticket 23).
// getRelativeDate() dropped: dead, zero callers anywhere in the codebase.
//
// multiArraySearch() (called by SyndicationAction to detect whether a feed item was
// already imported as a Bazar entry) is NOT defined here: it's defined in
// tools/bazar/libs/bazar.fonct.php (a generic recursive multi-array search, not
// syndication-specific) and loaded via the normal extension-bootstrap mechanism
// whenever bazar is enabled -- a real cross-tool dependency, not a bug.

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
