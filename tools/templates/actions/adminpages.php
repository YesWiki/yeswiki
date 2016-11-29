<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->UserIsAdmin()) {
    $sql = 'SELECT tag,time,owner FROM '.$this->GetConfigValue('table_prefix').'pages '.
           'WHERE latest="Y" AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%"'.
           'ORDER BY tag';
    $pages = $this->LoadAll($sql);

    include_once 'tools/libs/squelettephp.class.php';
    $template_pages = new SquelettePhp('tools/templates/presentation/templates/pages_table.tpl.html');
    $template_pages->set(array('pages' => $pages)); // on passe le tableau de pages en parametres
    $output = $template_pages->analyser(); // affiche les resultats

    echo $output;
} else {
    echo '<div class="alert alert-danger">'.
         '    <strong>Action {{adminpages}}</strong> : '._t('TEMPLATE_ACTION_FOR_ADMINS_ONLY').
         '</div>'."\n";
}
