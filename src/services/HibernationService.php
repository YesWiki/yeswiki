<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HibernationService
{
    protected $params;
    protected $templateEngine;

    public function __construct(ParameterBagInterface $params, TemplateEngine $templateEngine)
    {
        $this->params = $params;
        $this->templateEngine = $templateEngine;
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
     * get alert message when hibernated.
     */
    public function getMessageWhenHibernated(): string
    {
        $message = [
            'type' => 'info',
            'message' => _t('WIKI_IN_HIBERNATION') . '<br/>',
        ];

        return $this->templateEngine->render('@core/alert-message-with-back.twig', $message);
    }
}
