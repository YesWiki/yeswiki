<?php

if (!function_exists('filterBodyByLanguage')) {
    /**
     * Keep only the {{lang="xx"}} section of $body matching $preferredLanguage, falling back to $defaultLanguage.
     */
    function filterBodyByLanguage(string $body, string $preferredLanguage, string $defaultLanguage): string
    {
        $chunks = preg_split('/({{lang="[a-zA-Z][a-zA-Z]*"}})/ms', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($chunks) <= 1) {
            return $body;
        }

        foreach ([$preferredLanguage, $defaultLanguage] as $wantedLanguage) {
            $found = null;
            for ($t = 1; $t < count($chunks); $t = $t + 2) {
                if (
                    preg_match('/{{lang="([a-zA-Z][a-zA-Z])*"}}/', $chunks[$t], $langToDisplay)
                    && $langToDisplay[1] == $wantedLanguage
                ) {
                    $found = $chunks[$t + 1];
                }
            }
            if ($found !== null) {
                return $found;
            }
        }

        return $body;
    }
}
