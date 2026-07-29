<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

class AddYeswikiReleaseConf extends YesWikiMigration
{
    public function run()
    {
        $params = $this->services->get(Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface::class);
        $releaseInConfig = $params->get('yeswiki_release');
        if ($releaseInConfig == _t('AU_UNKNOW') || !preg_match("/^\d{1,4}[.-].*/", $releaseInConfig)) {
            $config = $this->getService(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
            $config->load();
            $config['yeswiki_release'] = YESWIKI_RELEASE;
            $config->write();
        }
    }
}
