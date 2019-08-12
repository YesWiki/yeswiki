<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->UserIsAdmin()) {
    $sql = 'SELECT tag,time,owner FROM '.$this->GetConfigValue('table_prefix').'pages '.
           'WHERE latest="Y" AND comment_on="" AND tag NOT LIKE "LogDesActionsAdministratives%"'.
           'ORDER BY tag';
    $pages = $this->LoadAll($sql);

    include_once 'includes/squelettephp.class.php';
    try {
        $squel = new SquelettePhp('pages_table.tpl.html', 'templates');
        $output = $squel->render(
            array(
                'pages' => $pages
            )
        );
    } catch (Exception $e) {
        $output = '<div class="alert alert-danger">Erreur action {{adminpages ..}} : '.  $e->getMessage(). '</div>'."\n";
    }

    echo $output;
} else {
    echo '<div class="alert alert-danger">'.
         '    <strong>Action {{adminpages}}</strong> : '._t('TEMPLATE_ACTION_FOR_ADMINS_ONLY').
         '</div>'."\n";
}
