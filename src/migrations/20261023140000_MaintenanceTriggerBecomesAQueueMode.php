<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\Queue\QueueSettings;

/**
 * `maintenance_trigger` chose who ran the housekeeping sweep: a page view (`request`) or `./yeswicli core:maintenance` from a crontab (`cron`). The sweep is gone, the queue's mode of consumption decides now, and the setting is removed.
 *
 * A wiki on `cron` has a crontab: it moves to the `command` mode, whose crontab line is the one it
 * already has (`core:maintenance` stays an alias of `messenger:drain`). Unless a mode was already
 * chosen -- in the environment, private/.env or the configuration, even a mistyped one -- or the
 * binary serves the wiki and consumes by itself.
 *
 * The admin is told what changes: every background job, not only the housekeeping, now waits for
 * that crontab, which the old docs had run hourly. A `MAINTENANCE_TRIGGER` set in private/.env is
 * not the configuration and is not rewritten; it is said to do nothing any more.
 */
class MaintenanceTriggerBecomesAQueueMode extends YesWikiMigration
{
    private const SETTING = 'maintenance_trigger';

    /** The lock the sweep stamped; nothing reads it any more. */
    private const RETIRED_LOCK = 'cache/maintenance.lock';

    /** retire() removed the setting and set `queue_consumer` to `command`. */
    public const BECOMES_COMMAND = 'command';

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
     * @param self::BECOMES_COMMAND|self::REMOVED|null $outcome    what retire() did
     * @param string                                   $was        the setting's value, as var_export() writes it
     * @param string|null                              $envTrigger the value private/.env gives MAINTENANCE_TRIGGER, if it does
     */
    public static function advice(?string $outcome, string $was, ?string $envTrigger): ?string
    {
        $said = match ($outcome) {
            self::BECOMES_COMMAND => "maintenance_trigger ({$was}) was replaced by queue_consumer 'command': the housekeeping is a set of scheduled tasks of the background queue now, and your crontab line drains it (`./yeswicli core:maintenance` still works; `./yeswicli messenger:drain` is its new name). Every background job waits for it now, search indexing and fediverse deliveries included: run it every few minutes, not hourly.",
            self::REMOVED => "maintenance_trigger ({$was}) was removed: the housekeeping is a set of scheduled tasks of the background queue now, consumed as the queue's mode says.",
            default => null,
        };
        if ($envTrigger !== null) {
            $said = ($said === null ? '' : $said . ' ')
                . "private/.env sets MAINTENANCE_TRIGGER ('{$envTrigger}'), which does nothing any more: remove the line."
                . (strtolower(trim($envTrigger)) === 'cron'
                    ? ' For a crontab to keep doing the background work, set YESWIKI_QUEUE_CONSUMER=command there and run `./yeswicli messenger:drain` every few minutes.'
                    : '');
        }

        return $said;
    }

    /**
     * Removes the setting, moving a `cron` wiki that chose no mode to `command`; null when there was nothing to remove.
     *
     * @param (callable(): array<string, string>)|null $envFileValues private/.env content, injectable for tests (default: the wiki's own)
     *
     * @return self::BECOMES_COMMAND|self::REMOVED|null
     */
    public static function retire(ConfigurationFile $configuration, ?callable $envFileValues = null): ?string
    {
        if (!isset($configuration[self::SETTING])) {
            return null;
        }

        $trigger = strtolower(trim((string)$configuration[self::SETTING]));
        unset($configuration[self::SETTING]);

        // The file being migrated is what counts, not the configuration this process booted with.
        $settings = new QueueSettings(new ParameterBag(['queue_consumer' => $configuration['queue_consumer'] ?? '']), $envFileValues);
        if ($trigger !== 'cron' || $settings->origin() !== QueueSettings::ORIGIN_DEFAULT || $settings->isServedByBinary()) {
            return self::REMOVED;
        }

        $configuration['queue_consumer'] = QueueConsumerMode::Command->value;

        return self::BECOMES_COMMAND;
    }
}
