<?php

use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Core\YesWikiMigration;

class AddYeswikiReleaseConf extends YesWikiMigration
{
    public function run()
    {
        $params = $this->wiki->services->getParameterBag();
        $releaseInConfig = $params->get('yeswiki_release');
        if ($releaseInConfig == _t('AU_UNKNOW') || !preg_match("/^\d{1,4}[.-].*/", $releaseInConfig)) {
            $config = $this->wiki->services->get(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
            $config->load();
            $config['yeswiki_release'] = YESWIKI_RELEASE;
            $config->write();
        }
    }
}
