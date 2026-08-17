<?php

namespace YesWiki\Render\Service;

use YesWiki\Kernel\Service\HibernationService;

/** Renders the banner shown while the wiki is hibernated. */
class HibernationNotice
{
    protected HibernationService $hibernationService;
    protected TemplateEngine $templateEngine;

    public function __construct(HibernationService $hibernationService, TemplateEngine $templateEngine)
    {
        $this->hibernationService = $hibernationService;
        $this->templateEngine = $templateEngine;
    }

    /** get alert message when hibernated. */
    public function getMessageWhenHibernated(): string
    {
        return $this->templateEngine->render('@core/alert-message-with-back.twig', [
            'type' => 'info',
            'message' => $this->hibernationService->getHibernationMessageText(),
        ]);
    }
}
