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
// CVS : $Id: bazar.fonct.cal.php,v 1.7 2009/09/16 16:14:45 mrflos Exp $
/**
*
* Fonctions calendrier du module bazar
*
*@package bazar
//Auteur original :
*@author        David Delon <david.delon@clapas.net>
//Autres auteurs :
*@copyright     Tela-Botanica 2000-2004
*@version       $Revision: 1.7 $ $Date: 2009/09/16 16:14:45 $
// +------------------------------------------------------------------------------------------------------+
*/

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

require_once BAZ_CHEMIN.'libs/Calendar/Month/Weekdays.php';
require_once BAZ_CHEMIN.'libs/Calendar/Day.php';
require_once BAZ_CHEMIN.'libs/Calendar/Decorator.php';

// +------------------------------------------------------------------------------------------------------+
// |                                           LISTE de FONCTIONS                                         |
// +------------------------------------------------------------------------------------------------------+

// Classe Utilitaire pour Calendrier
class DiaryEvent extends Calendar_Decorator
{
    public $entry = array();
    public function DiaryEvent($calendar)
    {
        Calendar_Decorator::Calendar_Decorator($calendar);
    }
    public function setEntry($entry)
    {
        $this->entry[] = $entry;

    }
    public function getEntry()
    {
        return $this->entry;
    }
}

// $type : calendrier
// $type : calendrier_applette
function GestionAffichageCalendrier($type = 'calendrier')
{
    $retour = '<div class="'.$type.'">';

    $url = $GLOBALS['_BAZAR_']['url'] ;
    $db = &$GLOBALS['wiki'] ;

    // Nettoyage de l'url de la query string
    $chaine_url = $url->getQueryString();
    $tab_params = explode('&amp;', $chaine_url);
    if (count($tab_params) == 0) {
        $tab_params = explode('&', $chaine_url);
    }
    foreach ($tab_params as $param) {
        $tab_parametre = explode('=', $param);
        if ($tab_parametre[0]!='wiki') $url->removeQueryString($tab_parametre[0]);
    }

    if (!isset($_GET['y'])) {
        $_GET['y'] = date('Y');
    }

    if (!isset($_GET['m'])) {
        $_GET['m'] = date('m');
    }

    // 	Construction Mois en Cours
    $month = new Calendar_Month_Weekdays($_GET['y'],$_GET['m']);

    $curStamp = $month->getTimeStamp();
    $url->addQueryString('y', date('Y',$curStamp));
    $url->addQueryString('m', date('n',$curStamp));
    $url->addQueryString('d', date('j',$curStamp));
    $cur = str_replace('&','&amp;',$url->getUrl());

    // Gestion de l'affichage des titres des evenements
    if (isset($_GET['ctt']) && $_GET['ctt'] == '1') {
        $url->addQueryString('tt', '0');
        if (isset($_GET['tt']) && $_GET['tt'] == '0') {
            $url->addQueryString('tt', '1');
        }
        $tc_lien = str_replace('&','&amp;',$url->getUrl());
    } else {
        $url->addQueryString('tt', '0');
        if (isset($_GET['tt']) && $_GET['tt'] == '0') {
            $url->addQueryString('tt', '1');
        }
        $url->addQueryString('ctt', '1');
        $tc_lien = str_replace('&','&amp;',$url->getUrl());
    }
    $url->removeQueryString('ctt');
    $url->removeQueryString('tt');
    $tc_txt = _t('BAZ_AFFICHE_TITRES_COMPLETS');
    if (isset($_GET['tt']) && $_GET['tt'] == '0') {
        $tc_txt = _t('BAZ_TRONQUER_TITRES');
        $url->addQueryString('tt', $_GET['tt']);
    }

    // Navigation
    $prevStamp = $month->prevMonth(true);
    $url->addQueryString('y', date('Y',$prevStamp));
    $url->addQueryString('m', date('n',$prevStamp));
    $url->addQueryString('d', date('j',$prevStamp));
    $prev = str_replace('&','&amp;',$url->getUrl());

    $nextStamp = $month->nextMonth(true);
    $url->addQueryString('y', date('Y',$nextStamp));
    $url->addQueryString('m', date('n',$nextStamp));
    $url->addQueryString('d', date('j',$nextStamp));
    $next = str_replace('&','&amp;',$url->getUrl());

    // Suppression du parametre de troncage des titres
    $url->removeQueryString('tt');

    $fr_month = array(	"1"=>_t('BAZ_JANVIER'),"2"=>_t('BAZ_FEVRIER'),"3"=>_t('BAZ_MARS'),"4"=>_t('BAZ_AVRIL'),"5"=>_t('BAZ_MAI'),"6"=>_t('BAZ_JUIN'),
                        "7"=>_t('BAZ_JUILLET'),"8"=>_t('BAZ_AOUT'),"9"=>_t('BAZ_SEPTEMBRE'),"10"=>_t('BAZ_OCTOBRE'),"11"=>_t('BAZ_NOVEMBRE'),"12"=>_t('BAZ_DECEMBRE'));

    if ($type == 'calendrier' || $type == 'calendrierjquery' || $type == 'calendrierjquerymini') {
        $retour .= '<div class="cal_entete">'."\n";
        $retour .= '<span class="cal_navigation">'."\n";
        $retour .= '<a class="cal_precedent_lien" href="'.$prev.'" title="Allez au mois precedent"><img class="cal_precedent_img" src="'.BAZ_CHEMIN.'presentation/images/cal_precedent.png" alt="&lt;&lt;"/></a>'."\n";
        $retour .= '<a class="cal_mois_courrant" href="'.$cur.'">';
        $retour .= $fr_month[(date('n',$curStamp))];
        $retour .= '&nbsp;';
        $retour .= (date('Y',$curStamp));
        $retour .= '</a>'."\n";
        $retour .= '<a class="cal_suivant_lien" href="'.$next.'" title="Allez au mois suivant"><img class="cal_suivant_img" src="'.BAZ_CHEMIN.'presentation/images/cal_suivant.png" alt="&gt;&gt;"/></a>'."\n";
        $retour .= '</span>'."\n";
        if ($type == 'calendrier') $retour .= '<span class="tc_lien">'.'<a href="'.$tc_lien.'">'.$tc_txt.'</a>'.'</span>'."\n";
        $retour .= '</div>'."\n";
    }
    // Vue Mois calendrier ou vue applette

    if (!isset($_GET['id_fiche']) && ( $type == 'calendrier' || $type == 'calendrierjquery' || $type == 'calendrierjquerymini' )) {
        // Recherche evenement de la periode selectionnée
        $ts_jour_fin_mois = $month->nextMonth('timestamp');
        $ts_jour_debut_mois = $month->thisMonth('timestamp');

        //on recherche toutes les fiches puis on trie sur ceux qui possede une date
        $tableau_resultat = baz_requete_recherche_fiches('', 'chronologique', $GLOBALS['_BAZAR_']['id_typeannonce'], $GLOBALS['_BAZAR_']['categorie_nature']);
        $tab_fiches = array();
        foreach ($tableau_resultat as $fiche) {
            $valeurs_fiche = json_decode($fiche["body"], true);
            $valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
            //echo $valeurs_fiche['bf_titre'].' du '.$valeurs_fiche['bf_date_debut_evenement'].' au '.$valeurs_fiche['bf_date_fin_evenement'].'<br />';
            //echo 'date fin mois : '.date('Y-n-j', $ts_jour_fin_mois).'<br />';
            //echo 'date debut mois : '.date('Y-n-j', $ts_jour_debut_mois).'<br />';

            if (isset($valeurs_fiche['bf_date_debut_evenement'])) {
                $dateArr = explode("-", $valeurs_fiche['bf_date_debut_evenement']);
                $date1Int = mktime(0,0,0,$dateArr[1],$dateArr[2],$dateArr[0]);
            } else $date1Int = NULL;

            if (isset($valeurs_fiche['bf_date_fin_evenement'])) {
                $dateArr = explode("-", $valeurs_fiche['bf_date_fin_evenement']);
                $date2Int = mktime(0,0,0,$dateArr[1],$dateArr[2],$dateArr[0]);
            } else $date2Int = NULL;

            //echo ($date1Int < $ts_jour_fin_mois).' = ($date1Int < $ts_jour_fin_mois)';
            //echo ($date2Int >= $ts_jour_debut_mois).' = ($date2Int >= $ts_jour_debut_mois)';
            if ($date1Int && $date2Int) {
                $tab_fiches[] = $valeurs_fiche;
            }
        }

        $selection = array();
        $evenements = array();
        $annee = date('Y', $curStamp);
        $mois = date('m', $curStamp);
        $tablo_jours = array();
        foreach ($tab_fiches as $val_fiche) {
            list($annee_debut, $mois_debut, $jour_debut) = explode('-', $val_fiche['bf_date_debut_evenement']);
            list($annee_fin, $mois_fin, $jour_fin) = explode('-', $val_fiche['bf_date_fin_evenement']);

            $Calendrier = new Calendar($annee_debut, $mois_debut, $jour_debut);
            $ts_jour_suivant = $Calendrier->thisDay('timestamp');
            $ts_jour_fin = mktime(0,0,0,$mois_fin, $jour_fin, $annee_fin);

            $naviguer = true;
            while ($naviguer && ($ts_jour_suivant <= $ts_jour_fin)) {
                        // Si le jours suivant est inferieur a la date de fin du mois courrant, on continue...
                        if ($ts_jour_suivant < $ts_jour_fin_mois) {
                            $cle_j = date('Y-m-d', $ts_jour_suivant);
                            if (!isset($tablo_jours[$cle_j])) {
                                $tablo_jours[$cle_j]['Calendar_Day'] = new Calendar_Day(date('Y', $ts_jour_suivant),date('m', $ts_jour_suivant), date('d', $ts_jour_suivant));
                                $tablo_jours[$cle_j]['Diary_Event'] = new DiaryEvent($tablo_jours[$cle_j]['Calendar_Day']);
                            }
                            $tablo_jours[$cle_j]['Diary_Event']->setEntry($val_fiche['bf_titre']);

                            $ts_jour_suivant = $Calendrier->nextDay('timestamp');
                            //echo "ici$ts_jour_suivant-";
                            $Calendrier->setTimestamp($ts_jour_suivant);
                            //echo "la".$Calendrier->thisDay('timestamp')."-";
                        } else {
                            $naviguer = false;
                        }
            }
        }

        // Add the decorator to the selection
        foreach ($tablo_jours as $jour) {
            $selection[] = $jour['Diary_Event'];
        }

        // Affichage Calendrier
        $month->build($selection);
        $retour.= '<table>'."\n".
                '<colgroup>'."\n".
                    '<col class="cal_lundi"/>'."\n".
                    '<col class="cal_mardi"/>'."\n".
                    '<col class="cal_mercredi"/>'."\n".
                    '<col class="cal_jeudi"/>'."\n".
                    '<col class="cal_vendredi"/>'."\n".
                    '<col class="cal_samedi"/>'."\n".
                    '<col class="cal_dimanche"/>'."\n".
                '</colgroup>'."\n".
                '<thead>'."\n".
                 "<tr>"."\n";
        if ($type == 'calendrier' || $type == 'calendrierjquery') {
              $retour .= "<th> ". _t('BAZ_LUNDI') ."</th>
              <th> ". _t('BAZ_MARDI') ."</th>
              <th> ". _t('BAZ_MERCREDI') ."</th>
              <th> ". _t('BAZ_JEUDI') ."</th>
              <th> ". _t('BAZ_VENDREDI') ."</th>
              <th> ". _t('BAZ_SAMEDI') ."</th>
              <th> ". _t('BAZ_DIMANCHE') ."</th>
             </tr>
             ".'</thead>'."\n".'<tbody>'."\n";
        } elseif ($type == 'calendrierjquerymini') {
            $retour.= "<th> ". _t('BAZ_LUNDI_COURT') ."</th>
              <th> ". _t('BAZ_MARDI_COURT') ."</th>
              <th> ". _t('BAZ_MERCREDI_COURT') ."</th>
              <th> ". _t('BAZ_JEUDI_COURT') ."</th>
              <th> ". _t('BAZ_VENDREDI_COURT') ."</th>
              <th> ". _t('BAZ_SAMEDI_COURT') ."</th>
              <th> ". _t('BAZ_DIMANCHE_COURT') ."</th>
             </tr>
             ".'</thead>'."\n".'<tbody>'."\n";
        }

        $todayStamp=time();
        $today_ymd=date('Ymd',$todayStamp);

        // Other month : mois
        while ($day = $month->fetch() ) {
            $dayStamp = $day->thisDay(true);
            $day_ymd = date('Ymd',$dayStamp);
            if ( $day->isEmpty() ) {
                $class = "cal_autre_mois";
            } else {
                if (($day_ymd < $today_ymd)) {
                    $class= "cal_mois_precedent";
                } else {
                     if ($day_ymd == $today_ymd) {
                         $class= "cal_jour_courant";
                     } else {
                        $class="cal_mois_courant";
                     }
                }
            }

            $url->addQueryString ('y', date('Y',$dayStamp));
            $url->addQueryString ('m', date('n',$dayStamp));
            $url->addQueryString ('d', date('j',$dayStamp));
            $link = $url->getUrl();

            // isFirst() to find start of week
            if ($day->isFirst()) {
                $retour.= ( "<tr>\n" );
            }
            if ($type == 'calendrier') {
                $retour.= "<td class=\"".$class."\">".'<span class="cal_j">'.$day->thisDay().'</span>'."\n";
                if ($day->isSelected() ) {
                    $evenements = $day->getEntry();
                    $evenements_nbre = count($evenements);
                    $evenemt_xhtml = '';
                    while ($ligne_evenement = array_pop($evenements)) {
                        $id_fiches = $ligne_evenement->bf_id_fiche;
                        $url->addQueryString ('id_fiche',$id_fiches);
                        $link = str_replace('&','&amp;',$url->getUrl());

                        if (!isset($_GET['tt']) || (isset($_GET['tt']) && $_GET['tt'] == '1')) {
                            $titre_taille = strlen($ligne_evenement->bf_titre);
                            $titre = htmlentities(($titre_taille > 40)?substr($ligne_evenement->bf_titre, 0, 40).'...':$ligne_evenement->bf_titre, ENT_QUOTES);
                        } else {
                            $titre = htmlentities($ligne_evenement->bf_titre, ENT_QUOTES);
                        }
                        $evenemt_xhtml .= '<li class="tooltip" title="'.htmlentities($ligne_evenement->bf_titre, ENT_QUOTES).'"><a class="cal_evenemt" href="'.$link.'">'.$titre.'</a></li>'."\n";
                        $url->removeQueryString ('id_fiches');
                    }
                    if ($evenements_nbre > 0) {
                        $retour .= '<ul class="cal_evenemt_liste">';
                        $retour .= $evenemt_xhtml;
                        $retour .= '</ul>';
                    }
                }
            } elseif ($type == 'calendrierjquery' || $type == 'calendrierjquerymini') {
                if ($day->isSelected() ) {
                    $evenements = $day->getEntry();
                    $evenements_nbre = count($evenements);
                    $evenemt_xhtml = '';
                    while ($ligne_evenement = array_pop($evenements)) {
                        $id_fiches = $ligne_evenement->bf_id_fiche;
                        $url->addQueryString ('id_fiche',$id_fiches);
                        $link = str_replace('&','&amp;',$url->getUrl());
                        $titre = htmlentities($ligne_evenement->bf_titre, ENT_QUOTES);
                        $evenemt_xhtml .= '<li>
                        <span class="titre_evenement"><a class="cal_evenemt" href="'.$link.'">'.$titre.'</a></span>
                        </li>';
                        $url->removeQueryString ('id_fiches');
                    }
                    if ($evenements_nbre > 0) {
                        $retour .= '<td class="'.$class.' date_avec_evenements">'.$day->thisDay().'
                        <div class="evenements">
                        <ul>';
                        $retour .= $evenemt_xhtml;
                        $retour .= '</ul>
                        </div>';
                    } else {
                        $retour.= '<td class="'.$class.'">'.$day->thisDay()."\n";
                    }
                } else $retour .= '<td class="'.$class.'">'.$day->thisDay()."\n";

            } elseif ($type == 'calendriermini') {
                $lien_date= "<td class=\"".$class."\">".$day->thisDay();
                if ($day->isSelected() ) {
                    $evenements=$day->getEntry();
                    $id_fiches=array();
                    while ($ligne_evenement=array_pop($evenements)) {
                        $id_fiches[]=$ligne_evenement->bf_id_fiche;
                    }
                    $url->addQueryString ('id_fiches',$id_fiches);
                    $link = str_replace('&','&amp;',$url->getUrl());
                    $lien_date= "<td class=\"".$class."\"><a href=\"".$link."\">".$day->thisDay()."</a>\n";
                    $url->removeQueryString ('id_fiches');
                }
                $retour.=$lien_date;
            }
            $retour.= ( "</td>\n" );

            // isLast() to find end of week
            if ( $day->isLast() ) {
                $retour.= ( "</tr>\n" );
            }
        }
            $retour.= "</tbody></table>";
    }
    // Vue detail
    if ((isset($_GET['id_fiche']))) {
        // Ajout d'un titre pour la page avec la date
        $jours = array ('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche') ;
        $mois = array ('janvier', 'f&eacute;vrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'ao&ucirc;t', 'septembre',
                        'octobre', 'novembre', 'd&eacute;cembre') ;
        $timestamp = strtotime ($_GET['y'].'/'.$_GET['m'].'/'.$_GET['d']) ;

        $retour .= '<h1>'.$jours[date('w', $timestamp)-1].
                        ' '.$_GET['d'].' '.$mois[$_GET['m']-1].' '.$_GET['y'].'</h1>' ;
        $retour .= baz_voir_fiche(0,$_GET['id_fiche'] );

        // Un lien pour retourner au calendrier
        $url->removeQueryString('id_fiche');
        $url->removeQueryString('y');
        $url->removeQueryString('m');
        $url->removeQueryString('d');
        $url->addQueryString('y',$_GET['y']);
        $url->addQueryString('m',$_GET['m']);
        $url->addQueryString('d',$_GET['d']);
        $retour .= '<div class="retour"><a href="'.str_replace('&','&amp;',$url->getUrl()).'">Retour au calendrier</a></div>';
    }

    // Nettoyage de l'url
    $url->removeQueryString('id_fiche');
    $url->removeQueryString('y');
    $url->removeQueryString('m');
    $url->removeQueryString('d');

    return $retour."\n".'</div>'."\n";
}
