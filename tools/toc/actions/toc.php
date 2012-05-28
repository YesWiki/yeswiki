<?php

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$GLOBALS['tocaction']=0;

$tag = $this->GetPageTag();
$page = $this->LoadPage($tag);
$toc_body = $page["body"];

echo "<div class=\"toc\">\n";
if ($this->GetParameter("header"))
echo "<h1>".$this->Format($this->GetParameter("header"))."</h1>\n";
else
echo "<h1>Table des mati&egrave;res</h1>\n";
global $wiki;
$wiki=$this;

if (!function_exists("translate2toc"))
{
    function translate2toc($text)
    {
        global $wiki;
        $cur_text = $text;
        $l1=0;
        $l2=0;
        $l3=0;
        $l4=0;
        $l5=0;

        while ($cur_text)
        {
            if (! preg_match("/(={2,6})(.*)/ms", $cur_text, $matches))
                break;

            $cur_text=$matches[2];
            $class="";
            $endmatch="";
            if ($matches[1] == "======")
            { $l1++; $class="toc1"; $toc="TOC_1_".(2*$l1 - 1);$l1++;
                $endmatch="/(.*)======(.*?)/msU"; }
            else if ($matches[1] == "=====")
            { $l2++; $class="toc2"; $toc="TOC_2_".(2*$l2 - 1);$l2++;
                $endmatch="/(.*)=====(.*?)/msU"; }
            else if ($matches[1] == "====")
            { $l3++; $class="toc3"; $toc="TOC_3_".(2*$l3 - 1);$l3++;
                $endmatch="/(.*)====(.*?)/msU"; }
            else if ($matches[1] == "===")
            { $l4++; $class="toc4"; $toc="TOC_4_".(2*$l4 - 1);$l4++;
                $endmatch="/(.*)===(.*?)/msU"; }
            else if ($matches[1] == "==")
            { $l5++; $class="toc5"; $toc="TOC_5_".(2*$l5 - 1);$l5++;
                $endmatch="/(.*)==(.*?)/msU"; }
            else
                echo "????\n";

            if (! preg_match($endmatch, $cur_text, $matches))
                break;

            echo "<div class=\"$class\"><A Href=\"#$toc\">"
                .$wiki->Format(trim($matches[1]))."</A></div>\n";
            $cur_text = $matches[2];
        }
    }
}

translate2toc(preg_replace("/\"\".*?\"\"/ms", "", $toc_body));
echo "</div>\n";
?> 
