<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Entity\ConfigurationFile;

/**
 * Is the wiki hibernated?
 *
 * `archiving` is set and lifted by the backup that runs (ArchiveService::archive()), so a backup that dies leaves it behind. archive()
 * holds the archive lock while the status is set; whoever reads `archiving` and finds that lock free (or no lock file at all, as a
 * backup made before the lock leaves it) lifts the status: the run that set it is gone. It lifts it holding that lock, so that a backup
 * starting at that moment keeps the status it sets.
 */
class HibernationService
{
    public const HIBERNATED = ['hibernate', 'archiving', 'updating'];

    /** The lock a backup holds from just before it sets `archiving` until it has lifted it. */
    public const ARCHIVING_LOCK = 'cache/archiving.lock';

    protected ParameterBagInterface $params;

    private RuntimeLock $locks;

    public function __construct(ParameterBagInterface $params, ?RuntimeLock $locks = null)
    {
        $this->params = $params;
        $this->locks = $locks ?? new RuntimeLock();
    }

    /**
     * check if wiki_status is hibernated.
     *
     * The container's parameters are trusted, but for `archiving`: the config file and the archive lock are asked then, so that the first
     * request after a backup died lifts what it left, without anyone opening the backups page.
     *
     * @return bool true is in hibernation
     */
    public function isWikiHibernated(): bool
    {
        $status = $this->params->get('wiki_status');
        if ($status === 'archiving') {
            return $this->isWikiHibernatedNow();
        }

        return in_array($status, self::HIBERNATED);
    }

    /** The same question, asked of the config file as it is now rather than of the container's parameters. */
    public function isWikiHibernatedNow(): bool
    {
        return in_array($this->wikiStatusNow(), self::HIBERNATED, true);
    }

    /** `wiki_status` as the config file says it now, `running` when it says none, or when it says `archiving` and no run holds the archive lock: that status is then lifted. */
    public function wikiStatusNow(): string
    {
        $configuration = new ConfigurationService();
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $config = $configuration->getConfiguration($file);
        $config->load();
        $status = self::statusIn($config);

        if ($status === 'archiving' && $this->locks->probe(self::ARCHIVING_LOCK) !== true) {
            return $this->liftArchiving($configuration, $file, $config);
        }

        return $status;
    }

    /**
     * Lifts the `archiving` a gone backup left, under the archive lock, so that a backup starting meanwhile keeps the status it sets:
     * once the lock is taken the config file is read again, and the status lifted only if it still says `archiving`. A lock another
     * run took first is that backup's, whose `archiving` stays. No lock file (a backup from before the lock; reading the status creates
     * none), or one that cannot be opened or locked at all, tells nothing of a run: the status is lifted, since nothing may keep the wiki
     * read-only forever. Answers the status the wiki is left in.
     */
    private function liftArchiving(ConfigurationService $configuration, string $file, ConfigurationFile $config): string
    {
        if ($this->locks->lastTaken(self::ARCHIVING_LOCK) === 0) {
            return self::lift($configuration, $config);
        }
        $lock = $this->locks->acquire(self::ARCHIVING_LOCK);
        try {
            if (!$this->locks->tryLock($lock, $heldElsewhere)) {
                return $heldElsewhere ? 'archiving' : self::lift($configuration, $config);
            }
            $config = $configuration->getConfiguration($file);
            $config->load();
            $status = self::statusIn($config);

            return $status === 'archiving' ? self::lift($configuration, $config) : $status;
        } finally {
            $this->locks->release($lock);
        }
    }

    private static function lift(ConfigurationService $configuration, ConfigurationFile $config): string
    {
        unset($config['wiki_status']);
        $configuration->write($config);

        return 'running';
    }

    private static function statusIn(ConfigurationFile $config): string
    {
        $status = trim((string)($config['wiki_status'] ?? ''));

        return $status === '' ? 'running' : $status;
    }

    /** The message body shown while hibernated, as text. */
    public function getHibernationMessageText(): string
    {
        return _t('WIKI_IN_HIBERNATION') . '<br/>';
    }
}
