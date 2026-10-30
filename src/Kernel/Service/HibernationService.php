<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/** Is the wiki hibernated? */
class HibernationService
{
    /** The values of `wiki_status` that make the wiki read-only: set by an admin, a backup archive, an update. */
    public const HIBERNATED = ['hibernate', 'archiving', 'updating'];

    protected ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    /**
     * check if wiki_status is hibernated.
     *
     * @return bool true is in hibernation
     */
    public function isWikiHibernated(): bool
    {
        return in_array($this->params->get('wiki_status'), self::HIBERNATED);
    }

    /**
     * The same question, asked of the config file as it is now rather than of the parameters the container was built with.
     *
     * For code that runs long enough to see the status change under it -- a queue consumer that
     * blocks for an hour, a pass that runs the archive which sets `archiving` itself. Archives and
     * updates write the status to the config file (as ArchiveService::setWikiStatus() does), and
     * the parameters never follow. It reads and parses the file at each call, so a caller in a
     * loop spaces its calls.
     */
    public function isWikiHibernatedNow(): bool
    {
        $config = (new ConfigurationService())->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        return in_array(trim((string)($config['wiki_status'] ?? '')), self::HIBERNATED, true);
    }

    /** The message body shown while hibernated, as text. */
    public function getHibernationMessageText(): string
    {
        return _t('WIKI_IN_HIBERNATION') . '<br/>';
    }
}
