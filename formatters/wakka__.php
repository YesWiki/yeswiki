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
