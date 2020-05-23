<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// wow
if (preg_match('/(?=<[^>]+(?=[\s+\"\']wow[\s+\"\']).+)([^>]+>)/uU', $plugin_output_new)) {
    $this->addCSSFile('tools/templates/presentation/styles/animate.css');
    $this->addJavascriptFile('tools/templates/libs/vendor/wow.min.js');
    $this->addJavascript('$(document).ready(function() { new WOW().init(); });');
}
// markdown
if (preg_match('/(?=<[^>]+(?=[\s+\"\']markdown[\s+\"\']).+)([^>]+>)/uU', $plugin_output_new)) {
    $this->addJavascriptFile('tools/templates/libs/vendor/marked/marked.min.js');
    $this->addJavascript('$(document).ready(function() {
        $(\'.markdown\').each(function() {
            $(this).html(marked($(this).text()));
        }) 
    });');
}
// mermaid
if (preg_match('/(?=<[^>]+(?=[\s+\"\']mermaid[\s+\"\']).+)([^>]+>)/uU', $plugin_output_new)) {
    $this->addJavascriptFile('tools/templates/libs/vendor/mermaid/mermaid.min.js');
    $this->addJavascript('$(document).ready(function() { mermaid.initialize({startOnLoad:true}); });');
}
