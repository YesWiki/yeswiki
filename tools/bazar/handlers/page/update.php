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

//CE HANDLER SYNCHRONISE LES FICHES BAZAR SOUHAITEES AVEC LA TABLE users DU WIKINI,
//IL CREE DES NOUVEAUX UTILISATEURS WIKI SI LE NOM WIKI N'EXISTE PAS,
//IL MODIFIE LE MAIL ET LES MOTS DE PASSE DES NOMS WIKI EXISTANT DEJA
//UN MAIL EST ENVOYE A CHAQUE UTILISATEUR, POUR LUI RAPPELER SES IDENTIFIANTS, MOTS DE PASSE


// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}

if ($this->UserIsInGroup('admins')) {
    $sql = 'SELECT bl_label_liste, blv_label  FROM '.BAZ_PREFIXE.'liste, '.BAZ_PREFIXE.'liste_valeurs WHERE blv_ce_liste=bl_id_liste ORDER BY blv_ce_liste, blv_valeur';
    $tab = $this->LoadAll($sql);
    $anciennomliste ='';
    foreach ($tab as $ligne) {
        if ($ligne['bl_label_liste']!=$anciennomliste) {
            if (is_array($valeur)) {
                echo $nomwikiliste.' '.json_encode($valeur).'<br /><br />';
                //on sauve les valeurs d'une liste dans une PageWiki, pour garder l'historique
                $GLOBALS["wiki"]->SavePage($nomwikiliste, json_encode($valeur));
                //on cree un triple pour spécifier que la page wiki créée est une liste
                $GLOBALS["wiki"]->InsertTriple($nomwikiliste, 'http://outils-reseaux.org/_vocabulary/type', 'liste', '', '');
            }
            $valeur = NULL;
            $valeur = array();

            $nomwikiliste = genere_nom_wiki($ligne['bl_label_liste']);
            //on supprime les valeurs vides et on encode en utf-8 pour réussir é encoder en json
            $valeur["titre_liste"] = utf8_encode(htmlentities($ligne['bl_label_liste']));

            $anciennomliste = $ligne['bl_label_liste'];
            $i=1;
        }
        $valeur["label"][$i] = utf8_encode($ligne['blv_label']);
        $i++;
    }
}
