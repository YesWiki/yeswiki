<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// fonctions supplementaires a ajouter la classe wiki
$fp = @fopen('tools/login/libs/login.class.inc.php', 'r');
$contents = fread($fp, filesize('tools/login/libs/login.class.inc.php'));
fclose($fp);
$wikiClasses[] = 'Login';
$wikiClassesContent[] = str_replace('<?php', '', $contents);
