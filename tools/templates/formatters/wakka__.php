<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// Hack Hack Hack!!
// We just check if class attributes for js library exists to load the corresponding library and initialise it

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
            $(this).html(marked($(this).text(), {breaks: true, gfm: true,}));
        }) 
    });');
}

// mermaid
if (preg_match('/(?=<[^>]+(?=[\s+\"\']mermaid[\s+\"\']).+)([^>]+>)/uU', $plugin_output_new)) {
    $this->addJavascriptFile('tools/templates/libs/vendor/mermaid/mermaid.min.js');
    $this->addJavascript('$(document).ready(function() {
        mermaid.initialize({
            startOnLoad: true,
            fontFamily: \'inherit\',
            theme: "default",
            themeCSS: \':root { --mermaid-font-family: inherit;} .titleText, .taskText, .sectionTitle, .grid , .grid .tick text {font-family:inherit;}\'            
        });
    });');
}

// izmir
if (preg_match('/(?=<[^>]+(?=[\s+\"\']c4-izmir[\s+\"\']).+)([^>]+>)/uU', $plugin_output_new)) {
    $this->addCSSFile('tools/templates/libs/vendor/izmir/izmir.min.css');
}
