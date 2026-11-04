<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/** Is the wiki hibernated? */
class HibernationService
{
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

    /** The same question, asked of the config file as it is now rather than of the container's parameters. */
    public function isWikiHibernatedNow(): bool
    {
        return in_array($this->wikiStatusNow(), self::HIBERNATED, true);
    }

    /** `wiki_status` as the config file says it now, `running` when it says none. */
    public function wikiStatusNow(): string
    {
        $config = (new ConfigurationService())->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $status = trim((string)($config['wiki_status'] ?? ''));

        return $status === '' ? 'running' : $status;
    }

    /** The message body shown while hibernated, as text. */
    public function getHibernationMessageText(): string
    {
        return _t('WIKI_IN_HIBERNATION') . '<br/>';
    }
}
