<?php

// Execute le gestion des fichiers lier par l'action {{attach}}
// Necessite le fichier actions/attach.php pour fonctionner
// voir actions/attach.php ppour la documentation

//vérification de sécurité
if (!WIKINI_VERSION) {
    exit('acc&egrave;s direct interdit');
}

if ($this->HasAccess('write')) {
    if (!class_exists('attach')) {
        include 'tools/attach/libs/attach.lib.php';
    }
    $att = new attach($this);
    $att->doFilemanagerAction();
    unset($att);
} else {
    echo '<div class="alert alert-danger">' . _t('ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER') . '.</div>' . "\n";
}
