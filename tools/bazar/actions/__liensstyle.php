<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

//ajout des styles css pour bazar, le calendrier, la google map
echo '	<link rel="stylesheet" href="tools/bazar/presentation/styles/bazar.css" />'."\n".
'	<link rel="stylesheet" href="tools/bazar/libs/vendor/bootstrap/css/bootstrap.min.css" />'."\n".
'	<link rel="stylesheet" href="tools/bazar/libs/fullcalendar/fullcalendar.css" />'."\n";

$GLOBALS['js'] = (isset($GLOBALS['js']) ? $GLOBALS['js'] : '')."\n".'	<script type="text/javascript" src="tools/bazar/libs/vendor/bootstrap/js/bootstrap.min.js"></script>'."\n";