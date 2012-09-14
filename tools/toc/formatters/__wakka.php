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
                $toc="TOC_5_".(2*$l5 - 1);
                return "\"\"<h5 id=\"$toc\">";
            }

 

            // Fermeture du titre precedent
            else  
            {
                return "</h5>\"\"";
            }

        }


        // header level 4
        else if ($thing == "===")
        {
            static $l4 = 0;
            $br = 0;

            // Nouvelle occurence
            ++$l4;

            if ($l4 % 2)
            {
                $toc="TOC_4_".(2*$l4 - 1);
                return "\"\"<h4 id=\"$toc\">";
            }

 
            // Fermeture du titre precedent
            else  
            {
                return "</h4>\"\"";
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
                $toc="TOC_3_".(2*$l3 - 1);
                return "\"\"<h3 id=\"$toc\">";
            }

            // Fermeture du titre precedent
            else
            {
                return "</h3>\"\"";
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
                $toc="TOC_2_".(2*$l2 - 1);
                return "\"\"<h2 id=\"$toc\">";
            }

            // Fermeture du titre precedent
            else
            {
                return "</h2>\"\"";
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
                $toc="TOC_1_".(2*$l1 - 1);
                return "\"\"<h1 id=\"$toc\">";
            }

            // Fermeture du titre precedent
            else
            {
                return "</h1>\"\"";
            }
        }

        // if we reach this point, it must have been an accident.
        return $thing;
    }

}



if (preg_match_all ("/".'(\\{\\{toc)'.'(.*?)'.'(\\}\\})'."/is", $text, $matches)) {


    $text = preg_replace_callback(
            "/(======|=====|====|===|==|".
            "\n)/ms", "wakka2callbacktoc", $text);

}


?>
