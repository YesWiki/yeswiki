<?php

use YesWiki\Core\YesWikiLoader;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/**
 * Removes `maintenance_trigger`: the housekeeping sweep it chose a runner for is gone, the queue's mode decides now;
 * the admin is told when a crontab or a `MAINTENANCE_TRIGGER` in private/.env still refers to it.
 */
class MaintenanceTriggerBecomesAQueueMode extends YesWikiMigration
{
    private const SETTING = 'maintenance_trigger';

    private const RETIRED_LOCK = 'cache/maintenance.lock';

    public const CRON_REMOVED = 'cron';

    private const CRONTAB_IS_DEAD = '`./yeswicli core:maintenance` no longer exists: remove its line from your crontab. To consume the queue outside page views, choose the `worker` mode (a process kept alive by systemd or similar) or the `api` mode (an online cron calling /api/queue/consume); see INSTALL.md, Housekeeping.';

    public const REMOVED = 'removed';

    public function run()
    {
        try {
            $storage = $this->getService(Storage::class);
            if ($storage->exists(self::RETIRED_LOCK)) {
                $storage->delete(self::RETIRED_LOCK);
            }
        } catch (Throwable) {
        }

        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class)->getConfiguration($file);
        $configuration->load();

        $was = var_export($configuration[self::SETTING], true);
        $outcome = self::retire($configuration);
        if ($outcome !== null && !$configuration->write()) {
            throw new RuntimeException("could not write {$file}: maintenance_trigger is still there");
        }

        $envTrigger = YesWikiLoader::envFileValues()[strtoupper(self::SETTING)] ?? null;
        $advice = self::advice($outcome, $was, $envTrigger);
        if ($advice !== null) {
            $this->say($advice);
        }
    }

    /**
     * What the admin is told of the setting and of a `MAINTENANCE_TRIGGER` line in private/.env; null when neither existed.
     *
     * @param self::CRON_REMOVED|self::REMOVED|null $outcome
     */
    public static function advice(?string $outcome, string $was, ?string $envTrigger): ?string
    {
        $said = match ($outcome) {
            self::CRON_REMOVED => "maintenance_trigger ({$was}) was removed: the housekeeping is a set of scheduled tasks of the background queue now, consumed as the queue's mode says. " . self::CRONTAB_IS_DEAD,
            self::REMOVED => "maintenance_trigger ({$was}) was removed: the housekeeping is a set of scheduled tasks of the background queue now, consumed as the queue's mode says.",
            default => null,
        };
        if ($envTrigger !== null) {
            $said = ($said === null ? '' : $said . ' ')
                . "private/.env sets MAINTENANCE_TRIGGER ('{$envTrigger}'), which does nothing any more: remove the line."
                . (strtolower(trim($envTrigger)) === 'cron'
                    ? ' ' . self::CRONTAB_IS_DEAD
                    : '');
        }

        return $said;
    }

    /**
     * Removes the setting, saying whether it was `cron`; null when there was nothing to remove.
     *
     * @return self::CRON_REMOVED|self::REMOVED|null
     */
    public static function retire(ConfigurationFile $configuration): ?string
    {
        if (!isset($configuration[self::SETTING])) {
            return null;
        }

        $trigger = strtolower(trim((string)$configuration[self::SETTING]));
        unset($configuration[self::SETTING]);

        return $trigger === 'cron' ? self::CRON_REMOVED : self::REMOVED;
    }
}
