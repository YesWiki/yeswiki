<?php

use YesWiki\AutoUpdate\Service\AutoUpdateService;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Security\Controller\SecurityController;

class CercopitequePostInstall extends YesWikiMigration
{
    public function run()
    {
        $previousVersion = $this->getService(SecurityController::class)->filterInput(INPUT_GET, 'previous_version', FILTER_DEFAULT, true);
        if ($previousVersion === 'cercopitheque') {
            $config = $this->getService(ConfigurationService::class)->getConfiguration('wakka.config.php');
            $config->load();

            // check favorite_theme
            // If default theme was used, install new yeswikicerco extension to keep same look and feel
            $favoriteThemefromFile = $config['favorite_theme'] ?? '';
            if (empty($favoriteThemefromFile) || $favoriteThemefromFile == 'yeswiki') {
                $this->getService(AutoUpdateService::class)->upgrade('yeswikicerco');

                $config['favorite_theme'] = 'yeswikicerco';
                $config['favorite_style'] = $config['favorite_style'] ?? 'gray.css';
                $config['favorite_squelette'] = $config['favorite_squelette'] ?? 'responsive-1col.tpl.html';
                $config->write();
            }
        }
    }
}
