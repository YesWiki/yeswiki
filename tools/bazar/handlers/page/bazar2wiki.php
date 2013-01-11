<?php
/*
bazar2wiki.php

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
    //PARAMETRES A CHANGER EN FONCTION DU SITE
    //numéros d'identifiants des types de fiches correspondant à un annuaire dans bazar
    $idtemplatebazar = '9,15';
    //mot de passe générique associé à tous les comptes
    $motdepasse = 'reema';
    //message envoyé par mail pour le changement de mot de passe
    $lien = str_replace("/wakka.php?wiki=","",$this->config["base_url"]);
    $objetmail = '['.str_replace("http://","",$lien).'] Vos nouveaux identifiants sur le site '.$this->config["wakka_name"];

    $sql = 'SELECT bf_titre, bf_mail FROM '.BAZ_PREFIXE.'fiche WHERE bf_ce_nature IN ('.$idtemplatebazar.') AND (bf_id_fiche=793 OR bf_id_fiche=804)';
    $tab = $this->LoadAll($sql);
    foreach ($tab as $ligne) {
        $nomwiki = genere_nom_wiki($ligne['bf_titre']);
        echo 'BAZAR : '.$ligne['bf_titre'].' - '.$ligne['bf_mail'].'<br />';
        if ($existingUser = $this->LoadUser($nomwiki)) {
            $requete = "update ".$this->config["table_prefix"]."users set ".
            "email = '".mysql_escape_string($ligne['bf_mail'])."', ".
            "password = md5('".mysql_escape_string($motdepasse)."') ".
            "where name = '".$nomwiki."' limit 1";
        } else {
            $requete =  "insert into ".$this->config["table_prefix"]."users set ".
                        "signuptime = now(), ".
                        "name = '".mysql_escape_string($nomwiki)."', ".
                        "email = '".mysql_escape_string($ligne['bf_mail'])."', ".
                        "password = md5('".mysql_escape_string($motdepasse)."')";
        }
        $this->Query($requete);

        //on ajoute le nom wiki comme createur de sa fiche
        $requtilisateur = "UPDATE '.BAZ_PREFIXE.'fiche SET bf_ce_utilisateur=\"".mysql_escape_string($nomwiki)."\" WHERE bf_mail=\"".$ligne['bf_mail']."\"";
        $this->Query($requtilisateur);

        //envoi du mail
        $messagemail = "Bonjour à tous,
Toute l'équipe est heureuse de vous faire part de la mise en ligne du nouveau site internet coopératif du REEMA. Ce site arbore de nouvelles couleurs et de nouvelles fonctionnalités pour donner encore plus de vie à l'éducation à la montagne sur le massif alpin !
Voir le site à cette adresse : ".str_replace("/wakka.php?wiki=","",$this->config["base_url"])."
1- Un site coopératif pour l'éducation à la montagne
Comme précédemment un grand nombre des fonctions du site peuvent être utilisées par les acteurs du massif alpin. Toute personne, une fois inscrite, peut facilement et directement :
- annoncer ses activités grand public, : conférence, sortie, visite, etc ...,
- publier une actualité et l'enregistrer sur la carte des évènements : formation, séjour, annonce, etc ...,
- présenter une ressource pédagogique : animation, vidéo, exposition, etc ...,
- et s'enregistrer sur la carte des acteurs du massif alpin.
Cet outil a toujours pour objectif de rendre les acteurs autonomes pour la publication de leurs propre informations, en temps réel ...(juste après validation par le modérateur !)
2- Petite visite guidée, quelques pages à retenir
Pour avoir un aperçu du site, nous vous invitons à découvrir en particulier les pages suivantes :
- Espace Actualités :
voir http://www.reema.fr/wakka.php?wiki=AnnonceS
- Espace Ressources :
voir http://www.reema.fr/wakka.php?wiki=EspaceRessources
- Espace Emploi, stage et formation :
voir http://www.reema.fr/wakka.php?wiki=EmploisStages
- Pour découvrir les nouvelles fonctionnalités très intéressantes dans l'espace Actualités :
  - l'agenda : http://www.reema.fr/wakka.php?wiki=Calendrier
  - la cartographie des évènements :  http://www.reema.fr/wakka.php?wiki=CartoEvenements
3- A vous de jouer !
Votre inscription a été migrée de l'ancien au nouveau système, identifiez vous en haut à droite avec les informations suivantes :
Votre identifiant NomWiki : ".$nomwiki."
Votre mot de passe : ". $motdepasse . "\n
Votre fiche apparait alors, il ne vous reste plus qu'à descendre en bas de votre fiche et cliquer sur \"modifier la fiche\" pour actualiser vos informations.
Veillez surtout à changer votre mot de passe pour des raisons de sécurité !
4- Des wikinis Acteurs
Des espaces projets peuvent être mis à disposition. Ceux sont des espaces internet (virtuel) ou wikini dans lesquels des acteurs du REEMA peuvent se regrouper pour échanger des informations ou mener un projet en commun (nous contacter pour en savoir plus).
Définition : le wikini est un logiciel de navigation sur internet collaboratif. Il suffit de double-cliquer sur chaque page. Le navigateur fonctionnera alors comme un logiciel de traitement de texte classique.
5 - En conclusion
Ce site est bien sûr évolutif ! N'hésitez pas à nous faire remonter vos remarques et à proposer du contenu pour l'alimenter.
Nous restons à votre disposition pour vous accompagner dans la découverte et l'utilisation de cette nouvelle version.

A très bientôt !

Sylvie Vernet, webmestre

Ce site a été réalisé par l'équipe d'Outils-Réseaux :
- Florian Schmitt, David Delon, informaticien développeur de logiciel libre, concepteur d'outils et de sites collaboratifs, soutien technique en informatique oour les acteurs de l'économie sociale et solidaire
- Jessica Deschamps, graphiste
- Laurent Marseault : animateur, formateur, consultant
Merci à eux pour leur travail !";
        echo 'WIKINI : '.$requete.'<br />'.'<strong>'.$objetmail.'</strong><br />'.nl2br($messagemail).'<hr />';

        $headers =   'From: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
                     'Reply-To: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
                     'X-Mailer: PHP/' . phpversion();
        mail($ligne['bf_mail'], remove_accents($objetmail), $messagemail, $headers);
    }
    echo '<br /><br /><a href="'.$this->href().'">Cliquez ici pour retourner au wikini</a>';
} else die ('Seuls les admins peuvent lancer cette op&eacute;ration.<br /><br />
            <a href="'.$this->href().'">Cliquez ici pour retourner au wikini</a>');
