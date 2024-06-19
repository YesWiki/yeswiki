<?php

// Execute le gestion des fichiers lier par l'action {{attach}}
// Necessite le fichier actions/attach.php pour fonctionner
// voir actions/attach.php ppour la documentation

//vérification de sécurité
if (!WIKINI_VERSION) {
    exit('acc&egrave;s direct interdit');
}
ob_start();
?>
<div class="page">
<?php
if ($this->UserIsOwner() || $this->UserIsAdmin()) {
    if (!class_exists('attach')) {
        include 'tools/attach/libs/attach.lib.php';
    }
    $att = new attach($this);
    $att->doFilemanager();
    unset($att);
} else {
    echo $this->Format('//' . _t('FILEMANAGER_ACTION_NEED_ACCESS') . '//');
}
?>
</div>
<?php
$output = ob_get_contents();
ob_end_clean();
echo $this->Header() . $output . $this->Footer(); ?>
