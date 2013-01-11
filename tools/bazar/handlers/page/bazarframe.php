<?php
/*
bazarframe.php

Copyright 2010  Florian SCHMITT
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

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die ("acc&egrave;s direct interdit");
}

if ($this->HasAccess("read")) {
    // on récupère les entêtes html mais pas ce qu'il y a dans le body
    $header =  explode('<body',$this->Header());
    echo $header[0]."<body>\n<div class=\"page-widget\">\n";

    //affichage de la page formatée juste avec le bazar
    echo $this->Format('{{bazar'.
    (isset($_GET['vue']) ? ' vue="'.$_GET['vue'].'"' : '').
    (isset($_GET['action']) ? ' action="'.$_GET['action'].'"' : '').
    (isset($_GET['id_typeannonce']) ? ' id_typeannonce="'.$_GET['id_typeannonce'].'"' : '').
    '" voirmenu="0"}}');
    echo "</div><!-- end div.page-widget -->";

    //javascript pour gerer les liens (ouvrir vers l'extérieur) dans les iframes
    $scripts_iframe = '<script>
    $(document).ready(function () {
        $("html").css({\'overflow-y\': \'auto\'});
        $("body").css({
                        \'background-color\' : \'transparent\',
                        \'background-image\' : \'none\',
                        \'text-align\' : \'left\',
                        \'width\' : \'auto\',
                        \'min-width\' : \'0\',
                    });

        $("a[href^=\'http://\']:not(a[href$=\'/slide_show\'])").click(function() {
            if (window.location != window.parent.location) {
                if (!($(this).hasClass("bouton_annuler"))) {
                    window.open($(this).attr("href"));

                    return false;
                }
            }
        });
    });
    </script>';
    $GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').$scripts_iframe ;

    //on récupère juste les javascripts et la fin des balises body et html
    $footer =  preg_replace('/^.+<script/Us', '<script', $this->Footer());
    echo $footer;
}
