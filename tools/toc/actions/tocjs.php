<?php

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
        die("acc&egrave;s direct interdit");
}

$tag = $this->GetPageTag();
$class = $this->GetParameter("class");
$offset = $this->GetParameter("offset");
if (empty($offset)) {
    $offset = '70';
}
$align = $this->GetParameter("align");
if (empty($align) || $align != 'left') {
    $align = 'right';
}
$size = $this->GetParameter("size");
if (empty($size)) {
    $size = '3';
}
$contentsize = 12 - intval($size);

$script = '
var align = "'.$align.'";
var page = $(".page:first");
var bootstrap3_enabled = (typeof $().emulateTransitionEnd == \'function\');
if (bootstrap3_enabled) {
    var rowclass=\'row\';
    var colclass=\'col-sm-\';
} else {
    var rowclass=\'row-fluid\';
    var colclass=\'span\';
}
if (align === "left") {
    page.addClass(colclass+"'.$contentsize.'").wrap( "<div class=\'"+rowclass+"\'></div>" ).parent().prepend( "<div class=\'"+colclass+"'.$size.' no-dblclick\'><div id=\'tocjs-'.$tag.'\' class=\'bs-sidebar hidden-print\' role=\'complementary\'></div></div>" );
}
else {
    page.addClass(colclass+"'.$contentsize.'").wrap( "<div class=\'"+rowclass+"\'></div>" ).parent().append( "<div class=\'"+colclass+"'.$size.' no-dblclick\'><div id=\'tocjs-'.$tag.'\' class=\'bs-sidebar hidden-print\' role=\'complementary\'></div></div>" );
}
$.gajus
    .contents({
        where: $(\'#tocjs-'.$tag.'\'),
        index: $(\'.page h1, .page h2, .page h3, .page h4, .page h5\')
    }).on(\'change.contents.gajus\', function (event, change) {
        if (change.previous) {
            //change.previous.heading.removeClass(\'active\');
            change.previous.anchor.removeClass(\'active\').parents(\'li\').removeClass(\'active\');
        }

        //change.current.heading.addClass(\'active\');
        change.current.anchor.addClass(\'active\').parents(\'li\').addClass(\'active\');
    });

    var $window = $(window)
    var $body = $(document.body)
    var pagestartHeight = page.offset().top;
    console.log(\'pagestartHeight\', pagestartHeight);
    var $sideBar = $(\'#tocjs-'.$tag.'\');

    $body.scrollspy({
        target: \'#tocjs-'.$tag.'\',
        offset: '.$offset.'
    });

    $(\'#tocjs-'.$tag.' a\').click(function (e) {
        e.preventDefault();

        var link = $(this).attr(\'href\');
        
        $(\'html, body\').animate({
            scrollTop: $(link).offset().top - '.$offset.'
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
            //console.log($sideBar.css(\'width\'));
          $sideBar.css(\'top\', pagestartHeight);
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
';
$this->AddJavascriptFile('tools/toc/libs/vendor/contents.min.js');
$this->AddJavascript($script);
echo '<style>
    #tocjs-'.$tag.'.affix {
        top : '.$offset.'px
    }

    #tocjs-'.$tag.' ol {
        list-style:none;
        padding:0;
        margin:0;
    }

    /* All levels of nav */
     #tocjs-'.$tag.' ol > li > a {
        display: block;
        color: #999999;
        font-size: 0.9em;
        font-weight: 500;
        padding: 4px 20px;
    }
    #tocjs-'.$tag.' ol > li > a:hover, #tocjs-'.$tag.' ol > li > a:focus {
        text-decoration: none;
        color: #444;
        background-color: transparent;
        border-left: 1px solid #444;
    }
    #tocjs-'.$tag.' ol > .active > a, #tocjs-'.$tag.' ol > .active:hover > a, #tocjs-'.$tag.' ol > .active:focus > a {
        font-weight: bold;
        color: #444;
        background-color: transparent;
        border-left: 2px solid #444;
    }
    /* Nav: second level (shown on .active) */
    #tocjs-'.$tag.' ol ol {
        display: none;
        margin-bottom: 8px;
    }
    #tocjs-'.$tag.' .active ol {
        display: block;
    }
    #tocjs-'.$tag.' ol ol > li > a {
        padding-top: 3px;
        padding-bottom: 3px;
        padding-left: 30px;
        font-size: 90%;
    }

    /* Show and affix the side nav when space allows it */
     @media screen and (min-width: 992px) {
        #tocjs-'.$tag.' ol > .active > ul {
            display: block;
        }
        /* Widen the fixed sidebar */
        #tocjs-'.$tag.'.affix, #tocjs-'.$tag.'.affix-bottom {
            width: 213px;
        }
        #tocjs-'.$tag.'.affix {
            position: fixed;
        }
        #tocjs-'.$tag.'.affix-bottom {
            position: absolute;
            /* Undo the static from mobile first approach */
        }
        #tocjs-'.$tag.'.affix-bottom #tocjs-'.$tag.', #tocjs-'.$tag.'.affix #tocjs-'.$tag.' {
            margin-top: 0;
            margin-bottom: 0;
        }
    }
    @media screen and (min-width: 1200px) {
        /* Widen the fixed sidebar again */
        #tocjs-'.$tag.'.affix-bottom, #tocjs-'.$tag.'.affix {
           // width: 263px;
        }
    }

    </style>'."\n";
