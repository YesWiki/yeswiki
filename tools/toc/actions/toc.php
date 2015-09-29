<?php

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$GLOBALS['tocaction'] = 0;

$tag = $this->GetPageTag();
$page = $this->LoadPage($tag);
$toc_body = $page["body"];
$class = $this->GetParameter("class");
$closed = $this->GetParameter("closed");
$title = $this->GetParameter("title");
if (empty($title)) $title = _t('TOC_TABLE_OF_CONTENTS');



echo "<div id=\"toc".$tag."\" class=\"toc well".(!empty($class) ? ' '.$class : '')."\">\n";

echo    "<div class=\"toc-title accordion-trigger\" data-toggle=\"collapse\" data-target=\"#toc-menu".$tag."\">".
'<span class="arrow">'.($closed==1 ? '&#9658;' : '&#9660;').'</span>&nbsp;<strong>'.$title."</strong>
</div><!-- /.toc-title -->\n
<div class=\"toc-menu\">
<div id=\"toc-menu".$tag."\" class=\"collapse".($closed==1 ? '' : ' in')."\">\n";

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

            echo "<li class=\"$class\"><a href=\"#$toc\">"
                .trim($matches[1])."</a></li>\n";
            $cur_text = $matches[2];
        }
    }
}

$script = "$(document).ready(function(){
    var toc = $('#toc".$tag."');   
    if (toc.length>0) {
        $('body').attr('data-spy','scroll');
            
        toc.scrollspy();
        var initialoffset = $('.page').offset().top;
        var divLocation = toc.offset();
        var diff = divLocation.top - initialoffset;

        // A la fin du chargement de la page, on positionne la table a la bonne position
        $(window).load(function () { 
            if ($(document).scrollTop() > divLocation.top) {
                offset = ($(document).scrollTop() - initialoffset + 20 ) + 'px';
                toc.animate({top:offset}, {duration:500,queue:false});
            }
        });

        // quand on scrolle, la table suit
        $(window).scroll(function () { 
            if ($(document).scrollTop() > divLocation.top) {
                offset = ($(document).scrollTop() - initialoffset + 20 ) + 'px';
                toc.animate({top:offset}, {duration:500,queue:false});
            }
            else {
                toc.animate({top:diff}, {duration:500,queue:false});
            }
        });

        // on anime le passage a un chapitre 
        $('.toc a').on('click', function () { 
            var link = $(this).attr('href');
            $('html, body').animate({
                 scrollTop: $(link).offset().top
             }, 500);
            return false;
        });
    }
});\n";
$this->AddJavascript($script);

    // on vérifie qu'il y est au moins un titre pour faire la liste
    if (preg_match("/(={2,6})(.*)/ms", $toc_body, $matches)) {
        echo    "<ul class=\"unstyled\">\n".
                    translate2toc(preg_replace("/\"\".*?\"\"/ms", "", $toc_body)).
                "</ul>\n";
    }
    
    // on ferme les divs ouvertes par l'action toc
    echo "</div><!-- /.toc-menu -->\n
    </div><!-- /#toc-menu".$tag." -->\n
    </div><!-- /#toc".$tag." -->\n";
?> 
