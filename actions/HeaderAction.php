<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\ThemeManager;

class HeaderAction extends YesWikiAction
{
    public function run()
    {
        $themeManager = $this->getService(ThemeManager::class);
        if (!$themeManager->loadTheme()) {
            $output = $themeManager->getErrorMessage();
            $text = "Cliquer pour installer le thème margot et réparer le site";
            $output .= '<div><a href="'.$this->wiki->Href('update').'" title="'.$text.'">'.$text.'</a></div>';
            return $output ;
        } else {
            return $themeManager->renderHeader() ;
        }
    }
}
