<?php

use YesWiki\Core\Controller\ThemeController;

$class = $this->getParameter('class');
if (
    $this->UserIsAdmin()
    && isset($_POST['action']) && ($_POST['action'] === 'setTemplate')
) {
    $this->Action('setwikidefaulttheme');
    // if not redirected by setwikidefaulttheme : redirect
    $this->Redirect($this->href('', $this->tag));
} else {
    echo $this->services->get(ThemeController::class)->showFormThemeSelector('selector', $class);
}
