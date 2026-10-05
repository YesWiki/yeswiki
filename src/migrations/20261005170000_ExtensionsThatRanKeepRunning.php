<?php

use YesWiki\Admin\Service\ExtensionActivation;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Entity\ExtensionFolders;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/** An Instance with no `active_extensions` yet keeps running the extensions its `desc.xml` files switched on (ADR-0029); the helloworld sample is not one of them. */
class ExtensionsThatRanKeepRunning extends YesWikiMigration
{
    private const SAMPLE = 'helloworld';

    public function run()
    {
        $configurationService = $this->getService(ConfigurationService::class);
        $configuration = $configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $configuration->load();
        if (isset($configuration[ExtensionActivation::CONFIG_KEY])) {
            return;
        }

        $kept = self::runningBefore(ExtensionFolders::visible(YESWIKI_PROGRAM_DIR, YESWIKI_INSTANCE_DIR));
        $configuration[ExtensionActivation::CONFIG_KEY] = $kept;
        $configurationService->write($configuration);

        $this->say($kept === []
            ? 'no extension was running, and from now on one runs only once it is switched on in /admin/updates'
            : 'the extensions that were running stay on: ' . implode(', ', $kept) . '; any other one now runs only once it is switched on in /admin/updates');
    }

    /**
     * @param array<string, string> $folders
     *
     * @return list<string>
     */
    public static function runningBefore(array $folders): array
    {
        return array_values(array_diff(ExtensionFolders::activeByLegacyDescriptor($folders), [self::SAMPLE]));
    }
}
