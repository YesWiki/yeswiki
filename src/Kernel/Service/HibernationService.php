<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\Lock\FileLocks;
use YesWiki\Kernel\Service\Lock\Locks;

/**
 * Is the wiki hibernated? A backup holds ARCHIVING_LOCK while it has set `archiving`; whoever reads `archiving` with that lock free
 * lifts the status, holding the lock, since the run that set it is gone.
 */
class HibernationService
{
    public const HIBERNATED = ['hibernate', 'archiving', 'updating'];

    /** The lock a backup holds from just before it sets `archiving` until it has lifted it. */
    public const ARCHIVING_LOCK = 'archiving';

    protected ParameterBagInterface $params;

    private Locks $locks;

    private ConfigurationService $configuration;

    public function __construct(ParameterBagInterface $params, ?Locks $locks = null, ?ConfigurationService $configuration = null)
    {
        $this->params = $params;
        $this->locks = $locks ?? new FileLocks();
        $this->configuration = $configuration ?? new ConfigurationService();
    }

    /** Whether the wiki is hibernated, from the container's parameters but for `archiving`, which is asked of the file and the archive lock. */
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
        $configuration = $this->configuration;
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $config = $configuration->getConfiguration($file);
        $config->load();
        $status = self::statusIn($config);

        if ($status === 'archiving') {
            $held = $this->locks->probe(self::ARCHIVING_LOCK);
            if ($held !== true) {
                return $this->liftArchiving($configuration, $file, $config, $held === null);
            }
        }

        return $status;
    }

    /** Lifts the `archiving` a gone backup left, under the archive lock and only if the file still says so; a lock nothing can tell of lifts it too. */
    private function liftArchiving(ConfigurationService $configuration, string $file, ConfigurationFile $config, bool $nothingTells): string
    {
        if ($nothingTells) {
            return self::lift($configuration, $config);
        }
        try {
            $heldElsewhere = false;
            if (!$this->locks->tryAcquire(self::ARCHIVING_LOCK, $heldElsewhere)) {
                return $heldElsewhere ? 'archiving' : self::lift($configuration, $config);
            }
            $config = $configuration->getConfiguration($file);
            $config->load();
            $status = self::statusIn($config);

            return $status === 'archiving' ? self::lift($configuration, $config) : $status;
        } finally {
            $this->locks->release(self::ARCHIVING_LOCK);
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
