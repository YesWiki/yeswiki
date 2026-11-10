<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/** Removes `maintenance_trigger`: the housekeeping it chose a runner for is a set of scheduled tasks of the queue now, and the queue's mode decides. */
class MaintenanceTriggerBecomesAQueueMode extends YesWikiMigration
{
    private const SETTING = 'maintenance_trigger';

    public function run()
    {
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class)->getConfiguration($file);
        $configuration->load();

        if (!isset($configuration[self::SETTING])) {
            return;
        }

        $was = var_export($configuration[self::SETTING], true);
        unset($configuration[self::SETTING]);
        if (!$configuration->write()) {
            throw new RuntimeException("could not write {$file}: maintenance_trigger is still there");
        }

        $this->say("maintenance_trigger ({$was}) was removed: the housekeeping is a set of scheduled tasks of the background queue now. The recommended mode is `request`, the default, which needs no setting: the queue is consumed at the end of page views. If a line of your crontab ran `./yeswicli core:maintenance`, replace it with `./yeswicli messenger:consume --drain`, every minute. Remove the MAINTENANCE_TRIGGER line of private/.env if there is one. The `worker` and `api` modes, and how to choose, are described in docs/fr/async.md.");
    }
}
