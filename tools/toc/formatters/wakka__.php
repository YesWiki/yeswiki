<?php

// Runs after the wakka formatter. If the page uses {{toc}}, assign each rendered <hN> tag
// the same "TOC_{level}_{n}" id that tools/toc/actions/toc.php's translate2toc() computes
// from the raw markdown, so the generated table of contents links scroll to the right heading.
// NB: this counts every <hN> tag in the final output, so a heading tag rendered directly by
// another action's own HTML (rather than by a markdown heading in the page body) would also
// get numbered and could throw the numbering out of sync with translate2toc().
if (preg_match('/\{\{toc\b/i', $text)) {
    $tocHeadingCounters = [];
    $plugin_output_new = preg_replace_callback(
        '/<h([1-6])((?:\s[^>]*)?)>/',
        function ($matches) use (&$tocHeadingCounters) {
            if (preg_match('/\sid="/', $matches[2])) {
                // already has an id (e.g. from another extension): don't override it
                return $matches[0];
            }

            $level = (int)$matches[1];
            $tocHeadingCounters[$level] = ($tocHeadingCounters[$level] ?? 0) + 1;

            return '<h' . $level . $matches[2] . ' id="TOC_' . $level . '_' . $tocHeadingCounters[$level] . '">';
        },
        $plugin_output_new
    );
}
