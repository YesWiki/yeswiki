<?php
/*
$Id: export.php,v 1.1 2010-07-22 14:21:10 mrflos Exp $
Copyright (c) 2010, Florian Schmitt <florian@outils-reseaux.org>
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}

echo $this->Header();
echo '<h1>Export</h1>'."\n";

if (isset($_POST['submit_export'])) {
    echo "export!";

} else {
    echo $this->FormOpen('export');
    $requete = "SELECT DISTINCT tag FROM ".$this->config["table_prefix"]."pages WHERE latest = 'Y' and comment_on = '' ORDER BY tag";
    $tab = $this->LoadAll($requete);

    if (is_array($tab)) {
        foreach ($tab as $nompage) {
            echo '<input type="checkbox" id="chk_'.$nompage['tag'].'" name="page_a_exporter" value="'.$nompage['tag'].'"><label for="chk_'.$nompage['tag'].'">&nbsp;'.
            $nompage['tag'].'&nbsp;<a class="voirpage" target="_blank" title="Voir la page '.$nompage['tag'].'" href="'.$this->href('htmlutf8', $nompage['tag']).'" rel="#overlay-action">(voir)</a>'.
            '</label><br />'."\n";
        }
    }
    echo '<input name="submit_export" type="submit" value="Exporter les pages sélectionnées" />'."\n";
    echo $this->FormClose();
}

echo $this->Footer();
