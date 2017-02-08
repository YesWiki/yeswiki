<?php

/*
trail.php : Permet d'afficher des liens "Page Suivante" "Sommaire" "Page Precedente" dans une page

Copyright 2003 Eric FELDSTEIN
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

/*
* Cette action permet de lier des pages entre elle via une page contenant la liste
* ordonnées de ces pages. L'action affiche des liens de navigation permettant de
* passer é la page suivante ou précédente ou de revenir au sommaire.
*
* @param toc string nom de la page contenant la liste ordonnée des pages é liées entre elles
*/

/* La page sommaire doit contenir une liste de pages. Le premier mot de chaque élément
   de la liste doit étre le nom d'une page du wiki, donc un mot wiki ou un lien force
   exemple de page sommaire:

===Sommaire===

 IntroductionAuProjet : présentation du projet.
 [[AnalyseProjet Analyse]] : analyse des besoins
   -BesoinDesUtilisateurs
   -ContraintesTechniques
 OutilsEtNormes

Texte texte  texte texte texte texte texte texte texte texte
texte texte texte texte texte texte texte texte texte texte texte
texte texte texte texte texte texte texte texte texte texte texte texte

*/

//echo $this->Format("===Action Trail===");
$sommaire = $this->GetParameter("toc");
if (!$sommaire) {
   echo '<div class="alert alert-danger"><strong>'._t('ERROR_ACTION_TRAIL').'</strong> : '._t('INDICATE_THE_PARAMETER_TOC').'.</div>'."\n";
}else{
   //chargement de la page sommaire
   $tocPage = $this->LoadPage($sommaire);
   if (!$tocPage)
   {
	   echo '<div class="alert alert-danger"><strong>'._t('ERROR_ACTION_TRAIL').'</strong> :é'._t('THE_PAGE').' ', $this->Link($sommaire), ' '._t('DOESNT_EXIST').' !</div>'."\n";
	   return;
   }
   //analyse de la page sommaire pour récupérer la liste des pages
   //recuperation de la liste
   if (preg_match_all("/\n[\t ]+(.*)/",$tocPage["body"],$tocListe)){
      //analyse de chaque ligne de la liste pour recupérer la page cible
      $currentPageIndex = NULL;
      foreach ($tocListe[1] as $line){
         //suppression d'un signe de liste eventuel
         $line = trim(preg_replace("/^([[:alnum:]]+\)|-)/","",$line));
         //recuperation du 1er mot
         $line = preg_replace("/^(\[\[.*\]\]|".WN_CHAR."+)\s*(.*)$/","$1",$line);
         //ajout a la liste des pages si le 1er mot est un lien force ou un mot wiki
         if (preg_match("/\[\[.*\]\]/",$line,$match)|$this->IsWikiName($line)){
            $pages[] = $line;
            //regarde si la page ajoute a la liste est la page courante
            if (strcasecmp($this->GetPageTag(),$line)==0){
               $currentPageIndex = count($pages)-1;
            }else {  //traite le cas des lien force
               if (preg_match("/\[\[(.*:)?".$this->GetPageTag()."(\s.*)?\]\]$/",$line)) {
                  $currentPageIndex = count($pages)-1;
               }
            }

         }
      }//foreach
   }
   //ecriture des liens Page Précedente/sommaire/page suivante
   if ($currentPageIndex>0) {
      $PrevPage = $pages[$currentPageIndex-1];
      $btnPrev = "<li class=\"previous\"><span class=\"trail_button\">".$this->Format("&larr; $PrevPage")."</span></li>\n";
   }else{
      $btnPrev = "";
   }
   $btnTOC = "<li><span class=\"trail_button\">".$this->ComposeLinkToPage($sommaire)."</span></li>\n";
   if ($currentPageIndex < (count($pages)-1)){
      $NextPage = $pages[$currentPageIndex+1];
      $btnNext = "<li class=\"next\"><span class=\"trail_button\">".$this->Format("$NextPage &rarr;")."</span></li>\n";
   }else{
      $btnNext = "";
   }
   echo '<ul class="pager">'."\n".$btnPrev.$btnTOC.$btnNext.'</ul>'."\n";
}
?>
