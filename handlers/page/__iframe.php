<?php

// Page translation for the iframe handler (formerly tools/lang's __iframe
// before-callback, which reused __show's filtering): keep only the
// {{lang="xx"}} section matching the visitor's language
require_once YESWIKI_SOURCE_DIR . '/src/lang.functions.php';
if (!empty($this->page['body'])) {
    $this->page['body'] = filterBodyByLanguage(
        $this->page['body'],
        $GLOBALS['prefered_language'],
        $this->config['default_language']
    );
}
