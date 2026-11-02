<?php

use YesWiki\Core\YesWikiLoader;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/**
 * `maintenance_trigger` chose who ran the housekeeping sweep: a page view (`request`) or `./yeswicli core:maintenance` from a crontab (`cron`). The sweep is gone, the queue's mode of consumption decides now, and the setting is removed.
 *
 * No mode is chosen in the admin's place: a wiki that chose none gets the default, the end of page
 * views (or the binary, which consumes by itself). A wiki on `cron` has a crontab running
 * `./yeswicli core:maintenance`, which is gone too: the admin is told to remove that line, and that
 * the `worker` or `api` mode is the way to consume the queue outside page views. A
 * `MAINTENANCE_TRIGGER` set in private/.env is not the configuration and is not rewritten; it is
 * said to do nothing any more.
 */
class MaintenanceTriggerBecomesAQueueMode extends YesWikiMigration
{
    private const SETTING = 'maintenance_trigger';

    /** The lock the sweep stamped; nothing reads it any more. */
    private const RETIRED_LOCK = 'cache/maintenance.lock';

    /** retire() removed the setting, which was `cron`: a crontab still runs the command that went with it. */
    public const CRON_REMOVED = 'cron';

    /** What a crontab that ran the sweep has to know. */
    private const CRONTAB_IS_DEAD = '`./yeswicli core:maintenance` no longer exists: remove its line from your crontab. To consume the queue outside page views, choose the `worker` mode (a process kept alive by systemd or similar) or the `api` mode (an online cron calling /api/queue/consume); see INSTALL.md, Housekeeping.';

    /** retire() only removed the setting. */
    public const REMOVED = 'removed';

    public function run()
    {
        try {
            $storage = $this->getService(Storage::class);
            if ($storage->exists(self::RETIRED_LOCK)) {
                $storage->delete(self::RETIRED_LOCK);
            }
        } catch (Throwable) {
            // A leftover in the cache does no harm, and `cache:clear --all` takes it.
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
     * What the admin is told: what became of the setting, and of a `MAINTENANCE_TRIGGER` line in private/.env; null when there was neither.
     *
     * @param self::CRON_REMOVED|self::REMOVED|null $outcome    what retire() did
     * @param string                                $was        the setting's value, as var_export() writes it
     * @param string|null                           $envTrigger the value private/.env gives MAINTENANCE_TRIGGER, if it does
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
