<?php
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if (!function_exists("wakka2callbacktoc"))
{
	
    function wakka2callbacktoc($things)
    {
            $thing = $things[1];

            global $wiki;
            
            static $numTitre = 0;
            
            
            if ($thing == "==")
            	    {
                        static $l5 = 0;
                        $br = 0;
                        
                        // Nouvelle occurence
                        ++$l5;

                        // Ouverture d'une balise de titre
                        if ($l5 % 2) 
                        {
                           ++$numTitre;
                           return "<h5><a name=\"$numTitre\">";
                        }

                        // Fermeture du titre precedent
                        else  
                        {
                           return "</a></h5>\n";
                        }

                }


        // header level 4
                else if ($thing == "===")
                {
                        static $l4 = 0;
                        $br = 0;
                        
                        // Nouvelle occurence
                        ++$l4;

                        // Ouverture d'une balise de titre
                        if ($l4 % 2)
                        {
                           ++$numTitre;
                           return "<h4><a name=\"$numTitre\">";
                        }

                        // Fermeture du titre precedent
                        else  
                        {
                           return "</a></h4>\n";
                        }
                }

        // header level 3
                else if ($thing == "====")
                {
                        static $l3 = 0;
                        $br = 0;

                        // Nouvelle occurence
                        ++$l3;

                        // Ouverture d'une balise de titre
                        if ($l3 % 2)
                        {
                           ++$numTitre;
                           return "<h3><a name=\"$numTitre\">";
                        }

                        // Fermeture du titre precedent
                        else
                        {
                           return "</a></h3>\n";
                        }
                }
                

        // header level 2
                else if ($thing == "=====")
                {
                        static $l2 = 0;
                        $br = 0;

                        // Nouvelle occurence
                        ++$l2;

                        // Ouverture d'une balise de titre
                        if ($l2 % 2)
                        {
                           ++$numTitre;
                           return "<h2><a name=\"$numTitre\">";
                        }

                        // Fermeture du titre precedent
                        else
                        {
                           return "</a></h2>\n";
                        }
                }
                

        // header level 1
                else if ($thing == "======")
                {
                        static $l1 = 0;
                        $br = 0;

                        // Nouvelle occurence
                        ++$l1;

                        // Ouverture d'une balise de titre
                        if ($l1 % 2)
                        {
                           ++$numTitre;
                           return "<h1><a name=\"$numTitre\">";
                        }

                        // Fermeture du titre precedent
                        else
                        {
                           return "</a></h1>\n";
                        }
                }
                
            // if we reach this point, it must have been an accident.
            return $thing;
    }
 
}

$plugin_output_new = preg_replace_callback("/(^\[\|.*?\|\])/ms", "wakka2callbacktoc", $plugin_output_new);
