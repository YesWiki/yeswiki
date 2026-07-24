<?php

if ($this->UserIsAdmin()) {
    $sql = "SELECT tag,time,owner FROM " . $this->GetConfigValue('table_prefix') . "pages " .
        "WHERE latest='Y' AND comment_on='' AND tag NOT LIKE 'LogDesActionsAdministratives%'" .
        "ORDER BY tag";
    $pages = $this->LoadAll($sql);

    echo $this->render('@core/pages_table.tpl.html', ['pages' => $pages]);
} else {
    echo '<div class="yw-alert yw-alert--danger">' .
        '    <strong>Action {{adminpages}}</strong> : ' . _t('TEMPLATE_ACTION_FOR_ADMINS_ONLY') .
        '</div>' . "\n";
}
