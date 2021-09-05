<?php

namespace YesWiki\Security\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiController;

class SecurityController extends YesWikiController
{
    protected $params;
    protected $templateEngine;

    public function __construct(
        TemplateEngine $templateEngine,
        ParameterBagInterface $params
    ) {
        $this->templateEngine = $templateEngine;
        $this->params = $params;
    }

    /**
     * check if wiki_status is hibernated
     * @return bool true is in hibernation
     */
    public function isWikiHibernated(): bool
    {
        return (in_array($this->params->get('wiki_status'), ['hibernate']));
    }

    /**
     * get alert message when hibernated
     * @return string
     */
    public function getMessageWhenHibernated():string
    {
        $message = [
            'type' => 'danger',
            'message' => _t('WIKI_IN_HIBERNATION') . "<br/>"
        ];
        return $this->templateEngine->render('@templates/alert-message-with-back.twig', $message);
    }
}
