<?php

// Hack Hack Hack!!
// We just check if class attributes for js library exists to load the corresponding library and initialise it

// wow
if (preg_match('/(?=<[^>]+(?=[\s+\"\']wow[\s+\"\']).+)([^>]+>)/uU', $plugin_output_new)) {
    $this->addCSSFile('styles/animate.css');
    $this->addJavascriptFile('javascripts/vendor/wow.min.js');
    $this->addJavascript('$(document).ready(function() { new WOW().init(); });');
}

// markdown
if (preg_match('/(?=<[^>]+(?=[\s+\"\']markdown[\s+\"\']).+)([^>]+>)/uU', $plugin_output_new)) {
    $this->addJavascriptFile('javascripts/vendor/marked/marked.min.js');
    $this->addJavascript('$(document).ready(function() {
        $(\'.markdown\').each(function() {
            $(this).html(marked($(this).text(), {breaks: true, gfm: true,}));
        }) 
    });');
}

// mermaid
if (preg_match('/<([a-z]+)([^>]*class="mermaid"[^>]*)>(.*?)<\/\1>/is', $plugin_output_new)) {
    $this->addJavascript('import mermaid from "./javascripts/vendor/mermaid/mermaid.esm.min.mjs";
     document.addEventListener("DOMContentLoaded", function() {
        mermaid.initialize({
            startOnLoad: true,
            fontFamily: \'inherit\',
            theme: "base",
            themeCSS: \':root { --mermaid-font-family: inherit;} .titleText, .taskText, .sectionTitle, .grid , .grid .tick text {font-family:inherit;} g.label {color:inherit;}\'            
        });
        })', true);
}

// izmir
if (preg_match('/(?=<[^>]+(?=[\s+\"\']c4-izmir[\s+\"\']).+)([^>]+>)/uU', $plugin_output_new)) {
    $this->addCSSFile('styles/vendor/izmir/izmir.min.css');
}

// toc: assign the same "TOC_{level}_{n}" id to each rendered <hN> that actions/toc.php's
// translate2toc() computes from the raw markdown, so the toc's generated links scroll to the
// right heading (ticket 13, formerly tools/toc/formatters/wakka__.php).
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
