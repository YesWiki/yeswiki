<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 4.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2004 Tela Botanica (accueil@tela-botanica.org)                                         |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: baz_langue_fr.inc.php,v 1.6 2010/03/04 14:19:04 mrflos Exp $
/**
* Fichier de traduction en francais de l'application Bazar
*
*@package bazar
//Auteur original :
*@author        Alexandre GRANIER <alexandre@tela-botanica.org>
*@author        Florian Schmitt <florian@ecole-et-nature.org>
//Autres auteurs :
*@author        Aucun
*@copyright     Tela-Botanica 2000-2004
*@version       $Revision: 1.6 $ $Date: 2010/03/04 14:19:04 $
// +------------------------------------------------------------------------------------------------------+
*/

define ('BAZ_FORMULAIRE', 'Formulaires');
define ('BAZ_LISTES', 'Listes');
define ('BAZ_NOM_LISTE', 'Nom de la liste');
define ('BAZ_VALEURS_LISTE', 'Valeurs de la liste');
define ('BAZ_AJOUTER_LABEL_LISTE', 'Ajouter une nouvelle valeur &agrave; la liste');
define ('BAZ_AJOUTER_CHAMPS_FORMULAIRE', 'Ajouter un nouveau champs au formulaire');
define ('BAZ_MODIFIER_FORMULAIRES', 'Gestion des formulaires');
define ('BAZ_GESTION_LISTES', 'Gestion des listes');
define ('BAZ_INTRO_MODIFIER_FORMULAIRE', 'Pour &eacute;diter un formulaire, cliquez sur son nom ou sur l\'icone avec le crayon.<br />Pour supprimer un formulaire, cliquez sur l\'icone de la corbeille.<br /> Pour cr&eacute;er un nouveau formulaire, cliquez sur le lien en dessous de cette liste.');
define ('BAZ_INTRO_AJOUT_LISTE',  '<strong>Pas de liste cr&eacute;&eacute;e pour l\'instant.</strong><br />Vous pouvez cr&eacute;er une nouvelle liste manuellement (cliquez sur le bouton avec un signe plus),<br />ou importer les listes d\'un YesWiki existant (comme http://yeswiki.net).');
define ('BAZ_CONFIRM_SUPPRIMER_FORMULAIRE', 'Attention! Toutes les donn&eacute;es enregistr&eacute;es seront perdues.. Etes-vous s&ucirc;rs de vouloir supprimer ce type de formulaire et toutes ses fiches associ&eacute;es');
define ('BAZ_CONFIRM_SUPPRIMER_LISTE', 'Attention! Toutes les donn&eacute;es enregistr&eacute;es seront perdues.. Etes-vous s&ucirc;rs de vouloir supprimer cette liste et toutes ses donn&eacute;es associ&eacute;es');
define ('BAZ_NOUVEAU_FORMULAIRE', 'Saisir un nouveau formulaire');
define ('BAZ_NOUVELLE_LISTE', 'Saisir une nouvelle liste');
define ('BAZ_FORMULAIRE_ET_FICHES_SUPPRIMES', 'Le type de formulaire et ses fiches associ&eacute;es ont bien &eacute;t&eacute; supprim&eacute;s.');
define ('BAZ_LISTES_SUPPRIMEES', 'La liste a bien &eacute;t&eacute; supprim&eacute;e.');
define ('BAZ_NOM_FORMULAIRE', 'Nom du formulaire');
define ('BAZ_EFFACER', 'Effacer');
define ('BAZ_TEMPLATE','Template');
define ('BAZ_CONDITION','Conditions de saisie');
define ('BAZ_AUTORISER_COMMENTAIRE','Autoriser les commentaires sur les fiches');
define ('BAZ_AUTORISER_APPROPRIATION','Autoriser l\'appropriation des fiches');
define ('BAZ_NOM_CLASSE_CSS','Nom de la classe CSS');
define ('BAZ_CATEGORIE_FORMULAIRE', 'Cat&eacute;gorie du formulaire');
define ('BAZ_NOUVEAU_FORMULAIRE_ENREGISTRE', 'Le nouveau formulaire a bien &eacute;t&eacute; enregistr&eacute;.');
define ('BAZ_NOUVELLE_LISTE_ENREGISTREE', 'La nouvelle liste a bien &eacute;t&eacute; enregistr&eacute;e.');
define ('BAZ_FORMULAIRE_MODIFIE', 'Le formulaire a bien &eacute;t&eacute; modifi&eacute;.');
define ('BAZ_LISTE_MODIFIEE', 'La liste a bien &eacute;t&eacute; modifi&eacute;e.');
define ('BAZ_CONFIRM_SUPPRIMER_FICHE', 'Etes vous s&ucirc;r de vouloir supprimer la fiche ?');
define ('BAZ_FICHE_SUPPRIMEE', 'La fiche a bien &eacute;t&eacute; supprim&eacute;e.');
define ('BAZ_FICHE_MODIFIEE', 'La fiche a bien &eacute;t&eacute; modifi&eacute;e.');
define ('BAZ_FICHE_VALIDEE', 'La fiche a bien &eacute;t&eacute; valid&eacute;e.');
define ('BAZ_FICHE_PAS_VALIDEE', 'La fiche a bien &eacute;t&eacute; invalid&eacute;e.');
define ('BAZ_FICHE_ENREGISTREE', 'La fiche a bien &eacute;t&eacute; enregistr&eacute;e.');
define ('BAZ_RECHERCHE_AVANCEE', 'Recherche avanc&eacute;e');
define ('BAZ_RECHERCHER_2POINTS', 'Rechercher : ');
define ('BAZ_TOUS_TYPES_FICHES', 'Tous types de fiches');
define ('BAZ_FICHE_NON_SAUVEE_PAS_DE_TITRE', 'La fiche n\'a pas pu &ecirc;tre sauv&eacute;e car elle ne poss&egrave; pas de titre.');
define ('BAZ_FLUX_RSS_GENERAL', 'Flux RSS de toutes les fiches');
define ('BAZ_MOT_CLE', 'mots cl&eacute;s (facultatif)');
define ('BAZ_DATE_CREATION', 'cr&eacute;&eacute;e le' );
define ('BAZ_DATE_MAJ', 'mise &agrave; jour le' );
define ('BAZ_TITREANNONCE', 'Titre de la fiche') ;
define ('BAZ_TYPE_FICHE', 'Type de fiche') ;
define ('BAZ_FICHIER_CSV_A_IMPORTER', 'Entrez le fichier CSV &agrave; importer');
define ('BAZ_EXEMPLE_FICHIER_CSV', 'Exemple de structuration du fichier CSV pour les fiches de type : ');
define ('BAZ_VISUALISATION_FICHIER_CSV_A_EXPORTER', 'Visualisation du fichier CSV pour l\'export des fiches de type : ');
define ('BAZ_TOTAL_FICHES', 'Total des fiches');
define ('BAZ_ENCODAGE_CSV', 'Le fichier CSV doit être encod&eacute; en UTF-8, avec des virgules (,) comme s&eacute;parateurs et des doubles guillemets (") pour les diff&eacute;rentes valeurs.<br />Les champs avec une &eacute;toile (*) sont obligatoires et doivent contenir des donn&eacute;es.');
define ('BAZ_IMPORTER_CE_FICHIER', 'Importer ce fichier');
define ('BAZ_IMPORTER', 'Importer');
define ('BAZ_EXPORTER', 'Exporter');
define ('BAZ_NON', 'Non') ;
define ('BAZ_ANNULER', 'Annuler') ;
define ('BAZ_VALIDER', 'Valider') ;
define ('BAZ_ETATPUBLICATION', 'Etat de publication') ;
define ('BAZ_PUBLIEE', 'Publi&eacute;e') ;
define ('BAZ_PAS_DE_FICHE', 'Vous n\'avez pas encore saisi de fiches.') ;
define ('BAZ_VOS_FICHES', 'Mes fiches saisies') ;
define ('BAZ_ERREUR_SAISIE', 'Erreur de saisie ') ;
define ('BAZ_VEUILLEZ_CORRIGER', 'Veuillez corriger') ;
define ('BAZ_MODIFIER', 'Modifier') ;
define ('BAZ_MODIFIER_LA_FICHE', 'Modifier la fiche') ;
define ('BAZ_SUPPRIMER', 'Supprimer') ;
define ('BAZ_SUPPRIMER_IMAGE', 'Supprimer l\'image') ;
define ('BAZ_CONFIRMATION_SUPPRESSION_IMAGE', 'Etes vous s&ucirc;rs de vouloir supprimer cette image?');
define ('BAZ_DROIT_INSUFFISANT', 'Op&eacute;ration permise au propri&eacutetaire de la fiche ou &agrave; un administrateur uniquement');
define ('BAZ_TITRE_SAISIE_FICHE', 'Saisir une fiche : ');
define ('BAZ_CHOIX_TYPE_FICHE', 'Choisissez le type de fiche que vous souhaitez d&eacute;poser:') ;
define ('BAZ_CONFIRMATION_SUPPRESSION', 'Etes-vous s&ucirc;r de vouloir supprimer cette fiche ?') ;
define ('BAZ_VALIDER_LA_FICHE', 'Valider la fiche') ;
define ('BAZ_PRECEDENT', 'Pr&eacute;c&eacute;dent') ;
define ('BAZ_SUIVANT', 'Suivant') ;
define ('BAZ_PAS_DE_FICHES', 'Pas de fiches trouv&eacute;es.');
define ('BAZ_S_ABONNER_AUX_FICHES', 'S\'abonner &agrave; un type de fiche');
define ('BAZ_S_ABONNER', 'S\'abonner');
define ('BAZ_RSS', 'Flux RSS');
define ('BAZ_DERNIERE_ACTU', 'Derni&egrave;res actualit&eacute;s');
define ('BAZ_DERNIERES_FICHES', 'Les derni&egrave;res fiches saisies');
define ('BAZ_CONSULTER','Rechercher');
define ('BAZ_SAISIR','Saisir');
define ('BAZ_NB_VUS',', consult&eacute;e ');
define ('BAZ_FOIS', ' fois depuis sa cr&eacute;ation.');
define ('BAZ_IL_Y_A', 'Il y a ');
define ('BAZ_VOIR_VOS_FICHES', 'Mes fiches');
define ('BAZ_RECHERCHER','Rechercher');
define ('BAZ_SAISIR_UNE_NOUVELLE_FICHE','Saisir une nouvelle fiche');
define ('BAZ_MODIFIER_IMAGE', ', ou modifier l\'image');
define ('BAZ_FICHIER', 'Le fichier ');
define ('BAZ_FICHIER_IMAGE_INEXISTANT', ' inexistant sur le serveur, la base de donn&eacute;es va être actualis&eacute;. Veuillez actualiser votre navigateur.');
define ('BAZ_CONFIRMATION_SUPPRESSION_FICHIER', 'Etes-vous s&ucirc;r de vouloir supprimer ce fichier ?') ;
define ('BAZ_SUPPRIMER_LA_FICHE', 'Supprimer la fiche');
define ('BAZ_INVALIDER_LA_FICHE', 'Invalider la fiche');
define ('BAZ_LATITUDE', 'Latitude');
define ('BAZ_LONGITUDE', 'Longitude');
define ('BAZ_VERIFIER_MON_ADRESSE', 'V&eacute;rifier mon adresse avec la carte');
define ('BAZ_PAS_DE_FORMULAIRES_TROUVES', 'Pas de formulaires trouv&eacute;s');
define ('BAZ_CHAMPS_REQUIS', 'champs requis');
define ('BAZ_FICHES', 'fiches.');
define ('BAZ_FICHE', 'fiche.');
define ('BAZ_FICHES_CORRESPONDANTES', 'fiches correspondantes &agrave; votre recherche') ;
define ('BAZ_FICHE_CORRESPONDANTE', 'fiche correspondante &agrave; votre recherche') ;
define ('BAZ_DESCRIPTION', 'Description') ;
define ('BAZ_DU', 'du') ;
define ('BAZ_AU', 'au') ;
define ('BAZ_ANONYME', 'Anonyme');
define ('BAZ_IDENTIFIEZ_VOUS_POUR_VOIR_VOS_FICHES', 'Pour voir vos fiches, veuillez vous identifier.');
define ('VERIFIER_MON_ADRESSE', 'Mettre le marqueur sur la carte grace &agrave; l\'adresse saisie');
define ('VERIFIER_MON_ADRESSE_CLIENT','Grace &agrave; votre acc&egrave;s Internet');
define ('TEXTE_POINT_DEPLACABLE', 'Si ce point ne correspond pas &agrave; votre adresse, vous pouvez le d&eacute;placer en cliquant gauche dessus et en laissant appuy&eacute;, afin de le faire correspondre parfaitement &agrave; votre adresse.');
define ('LATITUDE', 'Lat');
define ('LONGITUDE', 'Lon');
define ('BAZ_ECRITE', '&eacute;crite par');
define ('BAZ_CHOISIR', 'Choisir..');
define ('BAZ_CHOISIR_OBLIGATOIRE', 'Il faut choisir un &eacute;l&eacute;ment dans la liste d&eacute;roulante');
define ('BAZ_INDIFFERENT', 'Indiff&eacute;rent');
define ('BAZ_TELECHARGER_FICHIER_EXPORT_CSV', 'T&eacute;l&eacute;charger le fichier d\'export au format csv');
define ('BAZ_MOT_DE_PASSE', 'Mot de passe');
define ('BAZ_VERIFICATION', 'v&eacute;rification');
define ('BAZ_ACCEPTE_CONDITIONS', 'J\'accepte les conditions de saisie : ');
define ('BAZ_ACCEPTE_CONDITIONS_REQUIS', 'Il faut accepter les conditions de saisie');
define ('URL_LIEN_REQUIS', 'L\'adresse URL est requise');
define ('BAZ_PAS_DE_FICHES_UTILISATEUR_TROUVEE', 'Pas de fiche utilisateur associ&eacute;e &agrave; ce compte.');
define ('IMAGE_VALIDE_REQUIS','Ajouter une image &agrave; cette fiche est obligatoire');
define ('BAZ_A_ETE_EFFACE', ' a &eacute;t&eacute; effac&eacute;.');
define ('BAZ_PAS_DE_FICHES_SAUVEES_EN_VOTRE_NOM', 'Vous n\'avez aucune fiche enregistr&eacute;e pour l\'instant.');
define ('BAZ_DEPLACER_L_ELEMENT','D&eacute;placer l\'&eacute;l&eacute;ment');
define ('BAZ_LIST_NAME', 'Nom de la liste');
define ('BAZ_SAVE', 'Enregistrer');
define ('BAZ_KEY', 'Cl&eacute;');
define ('BAZ_TEXT', 'Texte');

//================ Calendrier Bazar =======================================
define ('BAZ_AFFICHE_TITRES_COMPLETS', 'Afficher les titres complets des &eacute;v&eacute;nements');
define ('BAZ_TRONQUER_TITRES', 'Tronquer les titres des &eacute;v&eacute;nements');
define ('BAZ_CALENDRIER','Calendrier');
define ('BAZ_LUNDI','Lundi');
define ('BAZ_MARDI','Mardi');
define ('BAZ_MERCREDI','Mercredi');
define ('BAZ_JEUDI','Jeudi');
define ('BAZ_VENDREDI','Vendredi');
define ('BAZ_SAMEDI','Samedi');
define ('BAZ_DIMANCHE','Dimanche');

define ('BAZ_LUNDI_COURT','Lun');
define ('BAZ_MARDI_COURT','Mar');
define ('BAZ_MERCREDI_COURT','Mer');
define ('BAZ_JEUDI_COURT','Jeu');
define ('BAZ_VENDREDI_COURT','Ven');
define ('BAZ_SAMEDI_COURT','Sam');
define ('BAZ_DIMANCHE_COURT','Dim');

define ('BAZ_JANVIER','Janvier');
define ('BAZ_FEVRIER','F&eacute;vrier');
define ('BAZ_MARS','Mars');
define ('BAZ_AVRIL','Avril');
define ('BAZ_MAI','Mai');
define ('BAZ_JUIN','Juin');
define ('BAZ_JUILLET','Juillet');
define ('BAZ_AOUT','Ao&uacute;t');
define ('BAZ_SEPTEMBRE','Septembre');
define ('BAZ_OCTOBRE','Octobre');
define ('BAZ_NOVEMBRE','Novembre');
define ('BAZ_DECEMBRE','D&eacute;cembre');
