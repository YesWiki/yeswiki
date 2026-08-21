<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/** Is the wiki hibernated? */
class HibernationService
{
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
        return in_array($this->params->get('wiki_status'), ['hibernate', 'archiving', 'updating']);
    }

    /** The message body shown while hibernated, as text. */
    public function getHibernationMessageText(): string
    {
        return _t('WIKI_IN_HIBERNATION') . '<br/>';
    }
}
