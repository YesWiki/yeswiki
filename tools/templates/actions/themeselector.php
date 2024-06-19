<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$class = $this->getParameter('class');
if ($this->UserIsAdmin()
    && isset($_POST['action']) && ($_POST['action'] === 'setTemplate')
) {
    $this->Action('setwikidefaulttheme');
    // if not redirected by setwikidefaulttheme : redirect
    $this->Redirect($this->href('', $this->tag));
} else {
    echo $this->services->get(\YesWiki\Templates\Controller\ThemeController::class)->showFormThemeSelector('selector', $class);
}
