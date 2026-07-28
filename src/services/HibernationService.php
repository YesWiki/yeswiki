<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Is the wiki hibernated?
 *
 * Deliberately depends on nothing but the parameter bag. Almost every low-level service
 * asks this question -- AclService, PageManager, UserManager, EntryManager, TemplateEngine
 * and a dozen more -- so whatever this class depends on is effectively depended on by the
 * whole core.
 *
 * It used to inject TemplateEngine so it could render its own hibernation banner, which
 * made a bottom-of-the-graph status check reach back up into rendering and closed 12
 * dependency cycles on its own (wave-two ticket 04). Rendering now lives in
 * HibernationNotice. Keep this class dependency-free.
 */
class HibernationService
{
    protected $params;

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

    /**
     * The message body shown while hibernated, as text. Rendering it is HibernationNotice's job.
     */
    public function getHibernationMessageText(): string
    {
        return _t('WIKI_IN_HIBERNATION') . '<br/>';
    }
}
