<?php
/*
$Id: deletepage.php 858 2007-11-22 00:46:30Z nepote $
Copyright 2002  David DELON
Copyright 2003  Eric FELDSTEIN
Copyright 2004  Jean Christophe ANDRÃ‰
Copyright 2006  Didier Loiseau
Copyright 2007  Charles NÃ‰POTE
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if (($this->UserIsOwner() || $this->UserIsAdmin())
        && isset($_GET['eraselink'])
        && $_GET['eraselink'] === 'oui'
        && isset($_GET['confirme'])
        && ($_GET['confirme'] === 'oui')
    ) {
    $inputToken = filter_input(INPUT_POST, 'csrf-token', FILTER_UNSAFE_RAW);
    $inputToken = in_array($inputToken,[false,null],true) ? $inputToken : htmlspecialchars(strip_tags($inputToken));
    if (!is_null($inputToken) && $inputToken !== false) {
        $tag = $this->GetPageTag();
        $token = new CsrfToken("handler\deletepage\\$tag", $inputToken);
        if ($this->services->get(CsrfTokenManager::class)->isTokenValid($token)) {
            $this->Query("DELETE FROM {$this->config["table_prefix"]}links WHERE to_tag = '$tag'");
        }
    }
}
