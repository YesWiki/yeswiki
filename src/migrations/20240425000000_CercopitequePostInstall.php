<?php

use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

class CercopitequePostInstall extends YesWikiMigration
{
    public function run()
    {
        $previousVersion = $this->getService(InputFilter::class)->filterInput(INPUT_GET, 'previous_version', FILTER_DEFAULT, true);
        if ($previousVersion === 'cercopitheque') {
            $config = $this->getService(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
            $config->load();

            // check favorite_theme
            // If default theme was used, install new yeswikicerco extension to keep same look and feel
            $favoriteThemefromFile = $config['favorite_theme'] ?? '';
            if (empty($favoriteThemefromFile) || $favoriteThemefromFile == 'yeswiki') {
                $this->getService(AutoUpdateService::class)->upgrade('yeswikicerco');

                $config['favorite_theme'] = 'yeswikicerco';
                $config['favorite_style'] = $config['favorite_style'] ?? 'gray.css';
                $config['favorite_squelette'] = $config['favorite_squelette'] ?? SQUELETTE_PAR_DEFAUT;
                $config->write();
            }
        }
    }
}
