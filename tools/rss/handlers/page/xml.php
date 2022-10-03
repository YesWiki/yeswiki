<?php
/**
 * handlers/page/xml.php
 *
 * Permet d'obtenir le contenu d'une page au format xml.
 */


header('Content-type: text/xml; charset='.YW_CHARSET);

if ($HasAccessRead = $this->HasAccess('read')) {
    // TODO : Return an empty xml ?
    // TODO : Return an error read (noaccess) xml ?
    // TODO : why only serve the body and not all page's properties ?
    // TODO : should exit after echoing ?
    if ($this->page) {
        // display page
        echo '<?xml version="1.0" encoding="'.YW_CHARSET.'"?>';
        echo $this->Format($this->page['body'], 'action');
    }
}
