<?php
/*

Copyright 2009  Florian SCHMITT
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

//CE HANDLER RECUPERE LES VALEURS DE LISTES des tables liste et liste_valeurs, pour les passer en page wiki

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}

$output = '';

if ($this->UserIsInGroup('admins')) {
    $req = 'SHOW TABLES FROM '.$this->config['mysql_database'].' LIKE "'.BAZ_PREFIXE.'liste%"';
    $tabnature = $this->LoadAll($req);
    if (is_array($tabnature)) {
        $sql = 'SELECT bl_label_liste, blv_valeur, blv_label  FROM '.BAZ_PREFIXE.'liste, '.BAZ_PREFIXE.'liste_valeurs WHERE blv_ce_liste=bl_id_liste AND blv_label!="Choisir..." ORDER BY blv_ce_liste, blv_valeur';
        $tab = $this->LoadAll($sql);
        $anciennomliste ='';$valeur = NULL;
        foreach ($tab as $ligne) {
            if ($ligne['bl_label_liste']!=$anciennomliste) {
                if (is_array($valeur)) {
                    $output .= $nomwikiliste.' '.json_encode($valeur).'<hr />';
                    //on sauve les valeurs d'une liste dans une PageWiki, pour garder l'historique
                    $GLOBALS["wiki"]->SavePage($nomwikiliste, json_encode($valeur));
                    //on cree un triple pour spécifier que la page wiki créée est une liste
                    $GLOBALS["wiki"]->InsertTriple($nomwikiliste, 'http://outils-reseaux.org/_vocabulary/type', 'liste', '', '');
                }
                $valeur = NULL;
                $valeur = array();

                $nomwikiliste = genere_nom_wiki(html_entity_decode('Liste '.$ligne['bl_label_liste']));
                //on supprime les valeurs vides et on encode en utf-8 pour réussir é encoder en json
                $valeur["titre_liste"] = utf8_encode(html_entity_decode($ligne['bl_label_liste']));

                $anciennomliste = $ligne['bl_label_liste'];
            }
            $valeur["label"][$ligne['blv_valeur']] = utf8_encode(html_entity_decode($ligne['blv_label']));
        }
        if ($output != '') $output = '<div class="info_box">Ces pages suivantes ont étés rajoutées:</div><div style="overflow:auto;width:100%;height:200px;">'.$output.'</div>'."\n";

        //on efface les tables qui servent plus
        $this->Query('DROP TABLE '.BAZ_PREFIXE.'liste, '.BAZ_PREFIXE.'liste_valeurs');
    }
    $repertoire = 'tools/bazar/install/formulaire/';
    $dir = opendir($repertoire); $tab_formulaire = array();
    while (false !== ($file = readdir($dir))) {
           if (substr($file, -4, 4)=='.sql') {
               $tab_formulaire[] = str_replace('.sql', '', $file);
        }
    }
    closedir($dir);

    foreach ($tab_formulaire as $formulaire) {
        $output .= $formulaire.'<input type="checkbox" name="forms[]" value="'.$formulaire.'"  /><br />';
    }
} else {
    $output .= '<div class="error_box">Seuls les admins peuvent lancer cette op&eacute;ration.</div>';
}

echo $this->Header();
echo "<div class=\"page\">\n$output\n<hr class=\"hr_clear\" />\n</div>\n";
echo $this->Footer();
