<?php

$GLOBALS['tocaction'] = 0;

$tag = $this->GetPageTag();
$page = $this->LoadPage($tag);
$toc_body = $page['body'] ?? '';
$class = $this->GetParameter('class');
$closed = $this->GetParameter('closed');
$title = $this->GetParameter('title');
if (empty($title)) {
    $title = _t('TOC_TABLE_OF_CONTENTS');
}

echo '<div id="toc' . $tag . '" class="toc well' . (!empty($class) ? ' ' . $class : '') . "\">\n";

echo '<div class="toc-title accordion-trigger" data-toggle="collapse" data-target="#toc-menu' . $tag . '">' .
    '<span class="arrow">' . ($closed == 1 ? '&#9658;' : '&#9660;') . '</span>&nbsp;<strong>' . $title . "</strong>
</div><!-- /.toc-title -->\n
<div class=\"toc-menu\">
<div id=\"toc-menu" . $tag . '" class="collapse' . ($closed == 1 ? '' : ' in') . "\">\n";

if (!function_exists('translate2toc')) {
    /**
     * Parses $text with the same CommonMark engine the wakka formatter uses, and builds
     * the <li> list of headings. Each heading gets the same "TOC_{level}_{n}" id that
     * tools/toc/formatters/wakka__.php assigns to the matching rendered <hN> tag.
     */
    function translate2toc($text)
    {
        $environment = new League\CommonMark\Environment\Environment();
        $environment->addExtension(new League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
        $environment->addExtension(new League\CommonMark\Extension\GithubFlavoredMarkdownExtension());
        $document = (new League\CommonMark\Parser\MarkdownParser($environment))->parse($text);

        $output = '';
        $counters = [];
        foreach ($document->iterator() as $node) {
            if (!$node instanceof League\CommonMark\Extension\CommonMark\Node\Block\Heading) {
                continue;
            }
            $level = $node->getLevel();
            $counters[$level] = ($counters[$level] ?? 0) + 1;
            $toc = 'TOC_' . $level . '_' . $counters[$level];

            $title = '';
            foreach ($node->iterator() as $inline) {
                if ($inline instanceof League\CommonMark\Node\StringContainerInterface) {
                    $title .= $inline->getLiteral();
                } elseif ($inline instanceof League\CommonMark\Node\Inline\Newline) {
                    $title .= ' ';
                }
            }

            $output .= '<li class="toc' . $level . '"><a href="#' . $toc . '">'
                . htmlspecialchars(trim($title), ENT_COMPAT, YW_CHARSET) . "</a></li>\n";
        }

        return $output;
    }
}

$script = "$(document).ready(function(){
    var toc = $('#toc" . $tag . "');   
    if (toc.length>0) {
        $('body').attr('data-spy','scroll');
            
        toc.scrollspy();
        var initialoffset = $('.page').offset().top;
        var divLocation = toc.offset();
        var diff = divLocation.top - initialoffset;

        // A la fin du chargement de la page, on positionne la table a la bonne position
        $(window).on('load',function () { 
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
$tocList = translate2toc($toc_body);
if ($tocList !== '') {
    echo "<ul class=\"unstyled\">\n" . $tocList . "</ul>\n";
}

// on ferme les divs ouvertes par l'action toc
echo "</div><!-- /.toc-menu -->\n
    </div><!-- /#toc-menu" . $tag . " -->\n
    </div><!-- /#toc" . $tag . " -->\n";
