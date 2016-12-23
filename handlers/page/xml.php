<?php
/**
 * handlers/page/xml.php
 *
 * Permet d'obtenir le contenu d'une page au format xml.
 *
 * $this -> Instance de Template

 * Copyright 2003  David DELON
 * Copyright 2003  Eric FELDSTEIN
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

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
