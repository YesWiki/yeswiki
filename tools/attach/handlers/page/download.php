<?php

# Execute le download des fichiers lier par l'action {{attach}}
# Necessite le fichier actions/attach.php pour fonctionner
# voir actions/attach.php ppour la documentation

if (!class_exists('attach')) {
    include('tools/attach/libs/attach.lib.php');
}
$att = new attach($this);
$att->doDownload();
unset($att);
