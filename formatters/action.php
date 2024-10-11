<?php

// This may look a bit strange, but all possible formatting tags have to be in a single regular expression for this to work correctly. Yup!

if (!function_exists('wakka2callback')) {
    function wakka2callback($things)
    {
        $thing = $things[1];

        global $wiki;

        // events
        if (preg_match("/^\{\{(.*?)\}\}$/s", $thing, $matches)) {
            if ($matches[1]) {
                return $wiki->Action($matches[1]);
            } else {
                return '{{}}';
            }
        } elseif (preg_match('/^.*$/s', $thing, $matches)) {
            return '';
        }

        // if we reach this point, it must have been an accident.
        return $thing;
    }
}

$text = str_replace("\r", '', $text);
$text = trim($text) . "\n";
$text = preg_replace_callback(
    "/(\{\{.*?\}\}|.*)/msU",
    'wakka2callback',
    $text
);

echo $text;
