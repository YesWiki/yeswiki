<?php

use YesWiki\Render\Service\ThemeSelectorRenderer;

$class = $this->getParameter('class');
if (
    $this->UserIsAdmin()
    && isset($_POST['action']) && ($_POST['action'] === 'setTemplate')
) {
    $this->Action('setwikidefaulttheme');
    // if not redirected by setwikidefaulttheme : redirect
    $this->Redirect($this->href('', $this->tag));
} else {
    echo $this->services->get(ThemeSelectorRenderer::class)->showFormThemeSelector('selector', $class);
}
