<?php

$class = $this->getParameter('class');
if (
    $this->UserIsAdmin()
    && isset($_POST['action']) && ($_POST['action'] === 'setTemplate')
) {
    $this->Action('setwikidefaulttheme');
    // if not redirected by setwikidefaulttheme : redirect
    $this->Redirect($this->href('', $this->tag));
} elseif (
    $this->UserIsAdmin()
    && isset($_POST['action']) && ($_POST['action'] === 'useSiteTheme')
) {
    $this->services->get(YesWiki\Core\Service\ThemeManager::class)->useSiteTheme($this->tag);
    $this->Redirect($this->href('', $this->tag));
} else {
    echo $this->services->get(YesWiki\Templates\Controller\ThemeController::class)->showFormThemeSelector('selector', $class);
}
