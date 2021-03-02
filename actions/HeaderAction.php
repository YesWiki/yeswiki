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
            $text = _t('THEME_MANAGER_CLICK_TO_INSTALL'). THEME_PAR_DEFAUT ._t('THEME_MANAGER_AND_REPAIR');
            $output .= '<div><a href="'.$this->wiki->Href('', 'GererMisesAJour', ['upgrade'=>  THEME_PAR_DEFAUT ]).'" title="'.$text.'">'.$text.'</a></div>';
            return $output ;
        } else {
            return $themeManager->renderHeader() ;
        }
    }
}
