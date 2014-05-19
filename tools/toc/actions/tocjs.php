<?php

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

$tag = $this->GetPageTag();
$class = $this->GetParameter("class");

$script = '$(document).ready(function(){
    var page = $(".page:first");
    var titles = page.find("h1,h2,h3,h4,h5");
    var bootstrap3_enabled = (typeof $().emulateTransitionEnd == \'function\');
    if (bootstrap3_enabled) {var rowclass=\'row\';} else {var rowclass=\'row-fluid\';}
    page.addClass("span9 col-md-9").wrap( "<div class=\'"+rowclass+"\'></div>" ).parent().append( "<div id=\'tocjs-'.$tag.'\' class=\'span3 col-md-3 no-dblclick\'><div class=\'bs-sidebar hidden-print\' role=\'complementary\'><ul class=\'nav bs-sidenav\'></ul></div></div>" );
    var toc = $("#tocjs-'.$tag.'");  
    var title, idtitle, typetitle, h1 = 0, h2 = 0, h3 = 0, h4 = 0, h5 = 0; 
    titles.each(function() {
        title = $(this);
        typetitle = title.prop("tagName");
        if (typetitle == \'H1\') {
            h1 += 1;
            idtitle = "H1-"+h1;
        }
        else if (typetitle == \'H2\') {
            h2 += 1;
            idtitle = "H2-"+h2;
        }
        else if (typetitle == \'H3\') {
            h3 += 1;
            idtitle = "H3-"+h3;
        }
        else if (typetitle == \'H4\') {
            h4 += 1;
            idtitle = "H4-"+h4;
        }
        else if (typetitle == \'H5\') {
            h5 += 1;
            idtitle = "H5-"+h5;
        }
        title.attr(\'id\', idtitle);
        toc.find(".bs-sidenav").append("<li class=\'"+typetitle+"\'><a class=\'toc-link\' href=\'#"+idtitle+"\'>"+title.html()+"</a></li>");
    });

    var $window = $(window)
    var $body = $(document.body)
    var pagestartHeight = page.offset().top;
    var $sideBar = $(\'.bs-sidebar\');
    var offsetnavbar = 70;

    $body.scrollspy({
        target: \'.bs-sidebar\',
        offset: offsetnavbar
    });

    $(\'.toc-link\').click(function (e) {
        e.preventDefault();

        var link = $(this).attr(\'href\');
        
        $(\'html, body\').animate({
            scrollTop: $(link).offset().top - offsetnavbar
        }, 500);
    })

    $window.on(\'resize\', function () {
        $body.scrollspy(\'refresh\')
        // We were resized. Check the position of the nav box
        $sideBar.affix(\'checkPosition\')
    })

    $window.on(\'load\', function () {
        $body.scrollspy(\'refresh\');
        $sideBar.affix({
            offset: {
                top: pagestartHeight,
                bottom: function () {
                    // We can\'t cache the height of the footer, since it could change
                    // when the window is resized. This function will be called every
                    // time the window is scrolled or resized
                    return $(\'.footer\').outerHeight(true)
                }
            }
        })
        $sideBar.on(\'affixed.bs.affix\', function (e) {
          $sideBar.css(\'top\', offsetnavbar);
        })
        $sideBar.on(\'affix-top.bs.affix\', function (e) {
          $sideBar.css(\'top\', 0);
        })


        setTimeout(function () {
            // Check the position of the nav box ASAP
            $sideBar.affix(\'checkPosition\')
        }, 10);
        setTimeout(function () {
            // Check it again after a while (required for IE)
            $sideBar.affix(\'checkPosition\')
        }, 100);
    });
});'."\n";
$this->AddJavascript($script);
echo '<style>
        /* First level of nav */
         .bs-sidenav {
            padding-top: 10px;
            padding-bottom: 10px;
            background-color: transparent;
        }
        /* All levels of nav */
         .bs-sidebar .nav > li > a {
            display: block;
            color: #999999;
            font-size: 0.9em;
            font-weight: 500;
            padding: 4px 20px;
        }
        .bs-sidebar .nav > li > a:hover, .bs-sidebar .nav > li > a:focus {
            text-decoration: none;
            color: #563d7c;
            background-color: transparent;
            border-left: 1px solid #563d7c;
        }
        .bs-sidebar .nav > .active > a, .bs-sidebar .nav > .active:hover > a, .bs-sidebar .nav > .active:focus > a {
            font-weight: bold;
            color: #563d7c;
            background-color: transparent;
            border-left: 2px solid #563d7c;
        }
        /* Nav: second level (shown on .active) */
         .bs-sidebar .nav .nav {
            display: none;
            /* Hide by default, but at >768px, show it */
            margin-bottom: 8px;
        }
        .bs-sidebar .nav .nav > li > a {
            padding-top: 3px;
            padding-bottom: 3px;
            padding-left: 30px;
            font-size: 90%;
        }

        /* Show and affix the side nav when space allows it */
         @media screen and (min-width: 992px) {
            .bs-sidebar .nav > .active > ul {
                display: block;
            }
            /* Widen the fixed sidebar */
            .bs-sidebar.affix, .bs-sidebar.affix-bottom {
                width: 213px;
            }
            .bs-sidebar.affix {
                position: fixed;
            }
            .bs-sidebar.affix-bottom {
                position: absolute;
                /* Undo the static from mobile first approach */
            }
            .bs-sidebar.affix-bottom .bs-sidenav, .bs-sidebar.affix .bs-sidenav {
                margin-top: 0;
                margin-bottom: 0;
            }
        }
        @media screen and (min-width: 1200px) {
            /* Widen the fixed sidebar again */
            .bs-sidebar.affix-bottom, .bs-sidebar.affix {
                width: 263px;
            }
        }

    </style>'."\n";
