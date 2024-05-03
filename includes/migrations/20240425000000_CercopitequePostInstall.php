<?php

use YesWiki\AutoUpdate\Service\AutoUpdateService;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\YesWikiMigration;

class CercopitequePostInstall extends YesWikiMigration
{
    public function run()
    {
        if (isset($_GET['previous_version']) && $_GET['previous_version'] == 'cercopitheque') {

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
