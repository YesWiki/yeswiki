<?php
/*
*/
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}


// Affichage uniquement du contenu correspondant à la langue en cours
 $translation_found=false;
 if (count($chunks=preg_split("/({{lang=\"[a-zA-Z][a-zA-Z]*\"}})/ms",$this->page["body"],-1,PREG_SPLIT_DELIM_CAPTURE))>1) {
    for ($t=1;$t<count($chunks);$t=$t+2) {
        if (preg_match("/{{lang=\"([a-zA-Z][a-zA-Z])*\"}}/",$chunks[$t],$lang_to_display)) {
          if ($lang_to_display[1]==$GLOBALS['prefered_language']) {
            $this->page["body"]=$chunks[$t+1];            
            $translation_found=true;
          }
        }
    }
    
    if (!$translation_found) {  // Pas de traduction ? Affichage de la langue par defaut
      for ($t=1;$t<count($chunks);$t=$t+2) {
        if (preg_match("/{{lang=\"([a-zA-Z][a-zA-Z])*\"}}/",$chunks[$t],$lang_to_display)) {
          if ($lang_to_display[1]== $this->config['default_language']) {
              $this->page["body"]=$chunks[$t+1];            
          }
        }
      }
    }
  }


?>
