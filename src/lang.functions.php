<?php

// Page-translation helpers (formerly tools/lang, integrated into core with
// ticket 25's revision): a page body can carry several language versions
// separated by {{lang="xx"}} markers; the show/iframe handlers and the
// {{include}} action keep only the visitor's preferred language's section
// (falling back to the wiki's default language).

if (!function_exists('filterBodyByLanguage')) {
    /**
     * Keep only the {{lang="xx"}} section of $body matching $preferredLanguage,
     * falling back to $defaultLanguage. A body without markers is returned as-is.
     */
    function filterBodyByLanguage(string $body, string $preferredLanguage, string $defaultLanguage): string
    {
        $chunks = preg_split('/({{lang="[a-zA-Z][a-zA-Z]*"}})/ms', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($chunks) <= 1) {
            return $body;
        }
        // last match wins for a given language, as in the original handler
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
